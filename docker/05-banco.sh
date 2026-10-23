#!/bin/sh
# Antes das automações do Laravel (migrate): garante o arquivo do banco SQLite dentro de storage/.
mkdir -p /var/www/html/storage/banco
[ -f /var/www/html/storage/banco/central.sqlite ] || touch /var/www/html/storage/banco/central.sqlite
