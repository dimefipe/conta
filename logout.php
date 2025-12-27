<?php
require_once __DIR__ . '/lib/helpers.php';
logout_user();
flash_set('ok','Sesión cerrada.');
redirect('login.php');
