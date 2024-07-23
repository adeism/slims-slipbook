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

if (!function_exists('pluginUrl'))
{
    /**
     * Generate URL with plugin_container.php?id=<id>&mod=<mod> + custom query
     *
     * @param array $data
     * @param boolean $reset
     * @return string
     */
    function pluginUrl(array $data = [], bool $reset = false): string
    {
        // back to base uri
        if ($reset) return Url::getSelf(fn($self) => $self . '?mod=' . $_GET['mod'] . '&id=' . $_GET['id']);
        
        return Url::getSelf(function($self) use($data) {
            return $self . '?' . http_build_query(array_merge($_GET,$data));
        });
    }
}

Plugins::getInstance()->registerMenu('bibliography', 'Slip Buku', __DIR__ . '/pages/print.php');