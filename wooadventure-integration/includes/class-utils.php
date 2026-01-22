<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Utils {

    public static function format_cpf( $cpf ) {
        $cpf = preg_replace( '/[^0-9]/', '', $cpf );
        if ( strlen( $cpf ) == 11 ) {
            return preg_replace( '/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf );
        }
        return $cpf;
    }

    // [NOVO] Função de Máscara para LGPD
    public static function mask_cpf( $cpf ) {
        $clean = preg_replace( '/[^0-9]/', '', $cpf );
        if ( strlen( $clean ) !== 11 ) {
            return '***.***.***-**';
        }
        // Exibe: 123.***.***-09
        return substr( $clean, 0, 3 ) . '.***.***-' . substr( $clean, -2 );
    }

    public static function is_valid_cpf( $cpf ) {
        $cpf = preg_replace( '/[^0-9]/', '', $cpf );
        if ( strlen( $cpf ) != 11 || preg_match( '/(\d)\1{10}/', $cpf ) ) {
            return false;
        }
        for ( $t = 9; $t < 11; $t++ ) {
            for ( $d = 0, $c = 0; $c < $t; $c++ ) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ( $cpf[$c] != $d ) return false;
        }
        return true;
    }

    public static function is_valid_date( $date ) {
        $d = DateTime::createFromFormat('d/m/Y', $date);
        return $d && $d->format('d/m/Y') === $date;
    }

    public static function is_min_age( $date, $years = 7 ) {
        $d = DateTime::createFromFormat('d/m/Y', $date);
        if (!$d) return false;
        $today = new DateTime();
        $age = $today->diff($d)->y;
        return $age >= $years;
    }

    public static function convert_date_to_db( $date_br ) {
        $date = DateTime::createFromFormat('d/m/Y', $date_br);
        return $date ? $date->format('Y-m-d') : '';
    }
}
