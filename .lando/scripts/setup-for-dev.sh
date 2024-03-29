#!/bin/bash

. /helpers/log.sh

lando_green "Cloning development repositories";

lando_yellow "Cloning Origins Modules"
rm -rf /app/web/modules/origins
git clone git@github.com:dof-dss/nicsdru_origins_modules.git /app/web/modules/origins

lando_pink "Cloning Origins Theme"
rm -rf /app/web/themes/custom/nicsdru_origins_theme
git clone git@github.com:dof-dss/nicsdru_origins_theme.git /app/web/themes/custom/nicsdru_origins_theme

lando_green "Go develop!";
