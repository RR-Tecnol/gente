#!/bin/sh
# Extrai colunas invalidas do log do Laravel
grep "Invalid column name" /var/www/storage/logs/laravel.log \
  | grep -o "Invalid column name '[^']*'" \
  | sort -u
