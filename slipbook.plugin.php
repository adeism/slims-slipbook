<?php
/**
 * Plugin Name: Slipbook
 * Plugin URI: -
 * Description: Plugin slipbook
 * Version: 1.0.0
 * Author: Drajat Hasan
 * Author URI: https://wa.me/send?phone=628973735575&text=Hai+Kak+saya+mau+pesan+plugin
 */
use SLiMS\Plugins;

Plugins::getInstance()->registerMenu('bibliography', 'Slip Buku', __DIR__ . '/pages/print.php');