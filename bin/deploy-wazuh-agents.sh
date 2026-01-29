#!/bin/bash
#
# Deploy Wazuh Agents a todos los contenedores Proxmox
# Ejecutar desde PIM server
#

PROXMOX_HOST="192.168.1.2"
PROXMOX_PASS="fr1t@ng@"
WAZUH_MANAGER="192.168.1.13"
WAZUH_VERSION="4.14.2"

# Contenedores a configurar (excluyendo Wazuh mismo)
CONTAINERS=(105 114 118 124 40001 40005 100000)

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Ejecutar comando en CT via SSH
pct_exec() {
    local ct_id=$1
    shift
    sshpass -p "$PROXMOX_PASS" ssh -o StrictHostKeyChecking=no root@$PROXMOX_HOST \
        "pct exec $ct_id -- bash -c '$*'" 2>/dev/null
}

# Obtener nombre del CT
get_ct_name() {
    sshpass -p "$PROXMOX_PASS" ssh -o StrictHostKeyChecking=no root@$PROXMOX_HOST \
        "pct config $1 | grep hostname | cut -d: -f2 | tr -d ' '" 2>/dev/null
}

# Verificar si el agente ya está instalado
check_agent() {
    local ct_id=$1
    pct_exec $ct_id "systemctl is-active wazuh-agent 2>/dev/null" | grep -q "active"
}

# Instalar agente Wazuh
install_agent() {
    local ct_id=$1
    local ct_name=$2
    
    log_info "[$ct_name] Instalando agente Wazuh..."
    
    # Detectar OS
    local os=$(pct_exec $ct_id "cat /etc/os-release | grep '^ID=' | cut -d= -f2 | tr -d '\"'")
    
    case $os in
        debian|ubuntu)
            # Añadir repositorio Wazuh
            pct_exec $ct_id "
                apt-get update -qq
                apt-get install -y -qq curl apt-transport-https gnupg2
                curl -s https://packages.wazuh.com/key/GPG-KEY-WAZUH | gpg --dearmor -o /usr/share/keyrings/wazuh.gpg
                echo 'deb [signed-by=/usr/share/keyrings/wazuh.gpg] https://packages.wazuh.com/4.x/apt/ stable main' > /etc/apt/sources.list.d/wazuh.list
                apt-get update -qq
                WAZUH_MANAGER='$WAZUH_MANAGER' apt-get install -y -qq wazuh-agent
                
                # Configurar manager
                sed -i 's/<address>.*<\/address>/<address>$WAZUH_MANAGER<\/address>/' /var/ossec/etc/ossec.conf
                
                # Iniciar servicio
                systemctl daemon-reload
                systemctl enable wazuh-agent
                systemctl start wazuh-agent
            "
            ;;
        *)
            log_error "[$ct_name] OS no soportado: $os"
            return 1
            ;;
    esac
}

# También instalar en el host PIM (este servidor)
install_local_agent() {
    log_info "[PIM-Server] Instalando agente Wazuh local..."
    
    if systemctl is-active wazuh-agent &>/dev/null; then
        log_warn "[PIM-Server] Agente ya instalado y activo"
        return 0
    fi
    
    curl -s https://packages.wazuh.com/key/GPG-KEY-WAZUH | gpg --dearmor -o /usr/share/keyrings/wazuh.gpg
    echo "deb [signed-by=/usr/share/keyrings/wazuh.gpg] https://packages.wazuh.com/4.x/apt/ stable main" > /etc/apt/sources.list.d/wazuh.list
    apt-get update -qq
    WAZUH_MANAGER="$WAZUH_MANAGER" apt-get install -y -qq wazuh-agent
    
    sed -i "s/<address>.*<\/address>/<address>$WAZUH_MANAGER<\/address>/" /var/ossec/etc/ossec.conf
    
    systemctl daemon-reload
    systemctl enable wazuh-agent
    systemctl start wazuh-agent
}

# MAIN
main() {
    echo "=========================================="
    echo "  Wazuh Agent Deployment"
    echo "  Manager: $WAZUH_MANAGER"
    echo "=========================================="
    echo ""
    
    # Instalar en este servidor (PIM)
    install_local_agent
    
    # Instalar en contenedores
    for ct_id in "${CONTAINERS[@]}"; do
        ct_name=$(get_ct_name $ct_id)
        
        if check_agent $ct_id; then
            log_warn "[$ct_name] Agente ya instalado y activo"
            continue
        fi
        
        install_agent $ct_id "$ct_name"
        
        # Verificar
        sleep 2
        if check_agent $ct_id; then
            log_info "[$ct_name] ✅ Agente instalado correctamente"
        else
            log_error "[$ct_name] ❌ Error instalando agente"
        fi
    done
    
    echo ""
    echo "=========================================="
    echo "  Despliegue completado"
    echo "  Dashboard: https://$WAZUH_MANAGER:443"
    echo "=========================================="
}

main "$@"
