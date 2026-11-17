<?php
// config/config.local.example.php
// Plantilla para PRODUCCIÓN. Pasos:
//   1) Copia este archivo en el hosting con el nombre  config.local.php  (dentro de la carpeta config/).
//   2) Escribe los datos reales de la base de datos que te da el hosting.
//   3) NUNCA subas config.local.php a GitHub (el .gitignore ya lo excluye).

define('APP_ENV', 'production');

define('DB_HOST', 'COLOCA_AQUI_EL_SERVIDOR_DE_LA_BASE_DE_DATOS');
define('DB_USER', 'COLOCA_AQUI_EL_USUARIO');
define('DB_PASS', 'COLOCA_AQUI_LA_CONTRASEÑA');
define('DB_NAME', 'COLOCA_AQUI_EL_NOMBRE_DE_LA_BASE_DE_DATOS');