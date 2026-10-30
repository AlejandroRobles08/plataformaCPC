#!/bin/bash

# ============================================================
# Plataforma CPC
# Script de gestión del entorno de desarrollo
# ============================================================

RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
WHITE='\033[0m'

DOCKER_NODE="plataformacpc-node"

print_text() {
    local text="$1"
    local color="$2"

    if [ -n "$color" ]; then
        echo -e "${color}${text}${WHITE}"
    else
        echo -e "${text}"
    fi
}

pause() {
    echo
    read -p "Presiona Enter para continuar..."
}

# ============================================================
# Verificar que estamos en la raíz del proyecto
# ============================================================

if [ ! -f "docker-compose.yml" ]; then
    print_text "ERROR: docker-compose.yml no encontrado." "$RED"
    print_text "Ejecuta este script desde la raíz de plataformaCPC." "$YELLOW"
    exit 1
fi

# ============================================================
# Iniciar con Docker
# ============================================================

start_docker() {

    print_text "" 
    print_text "Iniciando Plataforma CPC con Docker..." "$YELLOW"
    print_text ""

    # Verificar Docker
    if ! docker info > /dev/null 2>&1; then
        print_text "ERROR: Docker Engine no está disponible." "$RED"
        print_text "Verifica que Docker Desktop esté iniciado." "$YELLOW"
        return 1
    fi

    print_text "[OK] Docker Engine disponible." "$GREEN"

    # Detener Node si estuviera ejecutándose desde una sesión anterior
    if docker ps --format '{{.Names}}' | grep -q "^${DOCKER_NODE}$"; then
        print_text "[INFO] El servicio Node ya está ejecutándose." "$YELLOW"
    else
        print_text "[INFO] Iniciando servicios Docker..." "$YELLOW"

        if ! docker compose up -d; then
            print_text "ERROR: No se pudieron iniciar los servicios Docker." "$RED"
            return 1
        fi
    fi

    print_text ""
    print_text "Verificando servicios..." "$YELLOW"

    sleep 2

    docker compose ps

    print_text ""
    print_text "========================================" "$GREEN"
    print_text " Plataforma CPC iniciada con Docker" "$GREEN"
    print_text "========================================" "$GREEN"
    print_text ""
    print_text "Aplicación: http://localhost:8082"
    print_text "Vite:       http://localhost:5173"
    print_text ""
}

# ============================================================
# Iniciar con Laragon
# ============================================================

start_laragon() {

    print_text ""
    print_text "Iniciando Plataforma CPC con Laragon..." "$YELLOW"
    print_text ""

    # Verificar si Node de Docker está ejecutándose
    if docker info > /dev/null 2>&1; then

        if docker ps --format '{{.Names}}' | grep -q "^${DOCKER_NODE}$"; then

            print_text "[INFO] Vite de Docker está utilizando el puerto 5173." "$YELLOW"
            print_text "[INFO] Deteniendo Node de Docker..." "$YELLOW"

            docker compose stop node

            if [ $? -ne 0 ]; then
                print_text "ERROR: No se pudo detener Node de Docker." "$RED"
                return 1
            fi

            print_text "[OK] Node de Docker detenido." "$GREEN"

        else
            print_text "[OK] Vite de Docker ya está detenido." "$GREEN"
        fi

    fi

    print_text ""
    print_text "[INFO] Iniciando Vite con Laragon..." "$YELLOW"
    print_text ""

    npm run dev
}

# ============================================================
# Detener Docker
# ============================================================

stop_docker() {

    print_text ""
    print_text "Deteniendo Plataforma CPC..." "$YELLOW"
    print_text ""

    if ! docker info > /dev/null 2>&1; then
        print_text "ERROR: Docker Engine no está disponible." "$RED"
        return 1
    fi

    docker compose stop

    if [ $? -eq 0 ]; then
        print_text ""
        print_text "[OK] Plataforma CPC detenida." "$GREEN"
    else
        print_text ""
        print_text "ERROR: No se pudo detener Docker correctamente." "$RED"
        return 1
    fi
}

# ============================================================
# Menú principal
# ============================================================

while true; do

    clear

    print_text "========================================" "$GREEN"
    print_text "          PLATAFORMA CPC" "$GREEN"
    print_text "       Entorno de desarrollo" "$GREEN"
    print_text "========================================" "$GREEN"
    print_text ""
    print_text " [1] Iniciar con Docker"
    print_text " [2] Iniciar con Laragon"
    print_text " [3] Detener Docker"
    print_text " [4] Salir"
    print_text ""

    read -p "Seleccione una opción: " option

    case "$option" in

        1)
            start_docker
            pause
            ;;

        2)
            start_laragon
            ;;

        3)
            stop_docker
            pause
            ;;

        4)
            print_text ""
            exit 0
            ;;

        *)
            print_text ""
            print_text "Opción inválida. Selecciona un número del 1 al 4." "$RED"
            pause
            ;;

    esac

done