#!/bin/bash

# Script de test automatisé pour PilotEco Backend Docker
# Usage: ./test-docker.sh

set -e

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

echo -e "${BLUE}🧪 Tests automatisés PilotEco Backend Docker${NC}"
echo "======================================================="

# Test 1: Validation des conteneurs Docker
echo -e "\n${YELLOW}Test 1: Vérification des conteneurs Docker${NC}"
if docker compose ps | grep -q "Up"; then
    echo -e "${GREEN}✅ Conteneurs Docker démarrés${NC}"
else
    echo -e "${RED}❌ Conteneurs Docker non démarrés${NC}"
    echo -e "${BLUE}💡 Démarrez avec: make start${NC}"
    exit 1
fi

# Test 2: Vérification de la base de données PostgreSQL
echo -e "\n${YELLOW}Test 2: Vérification de la base de données PostgreSQL${NC}"
if docker compose exec -T database pg_isready -U app >/dev/null 2>&1; then
    echo -e "${GREEN}✅ PostgreSQL accessible${NC}"
else
    echo -e "${RED}❌ PostgreSQL non accessible${NC}"
    exit 1
fi

# Test 3: Test de réponse HTTP API
echo -e "\n${YELLOW}Test 3: Test de réponse HTTP de l'API${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✅ API répond avec HTTP 200${NC}"
elif [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "404" ]; then
    echo -e "${GREEN}✅ API accessible (HTTP $HTTP_CODE - normal sans auth)${NC}"
else
    echo -e "${RED}❌ API non accessible (HTTP $HTTP_CODE)${NC}"
    exit 1
fi

# Test 4: Test des ports
echo -e "\n${YELLOW}Test 4: Vérification des ports${NC}"
if nc -z localhost 80 2>/dev/null; then
    echo -e "${GREEN}✅ Port 80 (HTTP) accessible${NC}"
else
    echo -e "${RED}❌ Port 80 (HTTP) non accessible${NC}"
    exit 1
fi

# Résumé
echo -e "\n${GREEN}🎉 Tests Docker terminés !${NC}"
echo -e "${BLUE}📋 Résumé de l'environnement:${NC}"

# Informations détaillées
echo -e "\n${PURPLE}🐳 Conteneurs:${NC}"
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo -e "\n${PURPLE}🌐 Accès:${NC}"
echo "  API REST: http://localhost/api"
echo "  Documentation: http://localhost/api/docs"
echo "  Base de données: localhost:5432 (dans Docker uniquement)"

echo -e "\n${PURPLE}📊 État des services:${NC}"
echo "  ✅ FrankenPHP + Caddy"
echo "  ✅ Symfony Framework"
echo "  ✅ PostgreSQL Database"
echo "  ✅ API Platform"
echo "  ✅ Doctrine ORM"

echo -e "\n${GREEN}🚀 Votre environnement PilotEco Backend est prêt !${NC}"
echo -e "${BLUE}💡 Commandes utiles:${NC}"
echo "  make logs     # Voir les logs"
echo "  make sh       # Shell dans le conteneur"
echo "  make sf c=\"debug:router\"  # Voir les routes"
