<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Legal_Acceptances {
    public static function participant_status( $participant_id, $order_id, $departure_id = 0 ) {
        global $wpdb;
        $sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE participant_id = %d AND order_id = %d';
        $args = array( $participant_id, $order_id );
        if ( $departure_id ) { $sql .= ' AND departure_id = %d'; $args[] = $departure_id; }
        return $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) ? 'regular' : 'pending';
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'wcai_legal_acceptances'; }
    public static function create_table() {
        global $wpdb; $table = self::table(); $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            participant_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            departure_id bigint(20) unsigned DEFAULT NULL,
            document_id bigint(20) unsigned NOT NULL,
            document_version varchar(100) NOT NULL,
            document_hash char(64) NOT NULL,
            guardian_name varchar(255) DEFAULT NULL,
            guardian_cpf_hash char(64) DEFAULT NULL,
            acceptance_method varchar(30) NOT NULL,
            signature_post_id bigint(20) unsigned DEFAULT NULL,
            ip_hash char(64) DEFAULT NULL,
            user_agent_hash char(64) DEFAULT NULL,
            accepted_at datetime NOT NULL,
            PRIMARY KEY (id), KEY participant_document (participant_id, document_id), KEY order_id (order_id), KEY departure_id (departure_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php'; dbDelta( $sql );
    }
    public static function record( $data ) {
        global $wpdb;
        $required = array( 'participant_id', 'order_id', 'document_id', 'document_version', 'document_hash', 'acceptance_method' );
        foreach ( $required as $key ) if ( empty( $data[$key] ) ) return new WP_Error( 'wcai_acceptance_invalid', 'Dados de aceite incompletos.' );
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
        $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $row = array( 'participant_id'=>absint($data['participant_id']),'order_id'=>absint($data['order_id']),'departure_id'=>absint($data['departure_id']),'document_id'=>absint($data['document_id']),'document_version'=>sanitize_text_field($data['document_version']),'document_hash'=>sanitize_text_field($data['document_hash']),'guardian_name'=>isset($data['guardian_name'])?sanitize_text_field($data['guardian_name']):null,'guardian_cpf_hash'=>!empty($data['guardian_cpf'])?hash_hmac('sha256',preg_replace('/\D/','',$data['guardian_cpf']),wp_salt('auth')):null,'acceptance_method'=>sanitize_key($data['acceptance_method']),'signature_post_id'=>absint($data['signature_post_id']),'ip_hash'=>$ip?hash_hmac('sha256',$ip,wp_salt('auth')):null,'user_agent_hash'=>$agent?hash_hmac('sha256',$agent,wp_salt('auth')):null,'accepted_at'=>current_time('mysql',true) );
        $ok=$wpdb->insert(self::table(),$row); if(false===$ok)return new WP_Error('wcai_acceptance_failed','Não foi possível registrar o aceite.');
        WCAI_Audit_Log::log('legal_acceptance_recorded','legal_acceptance',$wpdb->insert_id,array('document_id'=>$row['document_id'],'version'=>$row['document_version'])); return $wpdb->insert_id;
    }
}
