<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Participants_DB {
    
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcai_participantes';
    }

    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            item_id bigint(20) DEFAULT 0,
            customer_id bigint(20) DEFAULT 0,
            nome_completo varchar(255) NOT NULL,
            cpf varchar(20) NOT NULL,
            data_nascimento date NOT NULL,
            ticket_hash varchar(64) DEFAULT NULL,
            checkin_status tinyint(1) DEFAULT 0,
            checkin_time datetime DEFAULT NULL,
            termo_assinado tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY cpf (cpf),
            KEY ticket_hash (ticket_hash)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    // Função auxiliar para tratar a data
    private static function prepare_date( $date ) {
        if ( empty($date) ) return '0000-00-00';
        
        // Se já tiver hífen, assume que é Y-m-d (banco) e não faz nada
        if ( strpos($date, '-') !== false ) {
            return $date;
        }
        // Se tiver barra, converte usando a Utils
        if ( strpos($date, '/') !== false && class_exists('WCAI_Utils') ) {
            return WCAI_Utils::convert_date_to_db( $date );
        }
        return $date;
    }

    public static function add( $data ) {
        global $wpdb;
        $defaults = array(
            'order_id' => 0,
            'item_id'  => 0,
            'customer_id' => get_current_user_id(),
            'nome_completo' => '',
            'cpf' => '',
            'data_nascimento' => '',
        );
        
        $data = wp_parse_args( $data, $defaults );
        
        // Sanitização
        $data['cpf'] = preg_replace('/[^0-9]/', '', $data['cpf']); 
        $data['data_nascimento'] = self::prepare_date( $data['data_nascimento'] );

        return $wpdb->insert( self::get_table_name(), $data );
    }

    public static function update( $id, $data ) {
        global $wpdb;
        
        if ( isset($data['cpf']) ) {
            $data['cpf'] = preg_replace('/[^0-9]/', '', $data['cpf']);
        }
        
        if ( isset($data['data_nascimento']) ) {
            $data['data_nascimento'] = self::prepare_date( $data['data_nascimento'] );
        }

        // Gera formatos dinâmicos para suportar colunas extras (ticket_hash, etc)
        $format = array();
        foreach($data as $key => $value) {
            $format[] = is_numeric($value) ? '%d' : '%s';
        }

        return $wpdb->update( 
            self::get_table_name(), 
            $data, 
            array( 'id' => $id ), 
            $format, 
            array( '%d' ) 
        );
    }

    public static function get_by_order( $order_id ) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE order_id = %d", $order_id ), ARRAY_A );
    }

    // Novo: Busca por Hash (Para o Scanner)
    public static function get_by_hash( $hash ) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ticket_hash = %s", $hash ), ARRAY_A );
    }

    public static function delete_by_order( $order_id ) {
        global $wpdb;
        return $wpdb->delete( self::get_table_name(), array( 'order_id' => $order_id ), array( '%d' ) );
    }
}
