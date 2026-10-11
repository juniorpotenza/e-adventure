<?php
/**
 * T8 - migração por passos: de 2.21.0 para a versão atual preserva dados.
 * Requer a suíte WP_UnitTestCase (phpunit.xml.dist). Não foi executado nesta sessão (PHP ausente).
 */
class Test_T8_Migration extends WP_UnitTestCase {

    private function insert_acceptance( $participant, $reservation, $version, $guardian = null, $hash = null, $signature_post = null ) {
        global $wpdb;
        $wpdb->insert(
            WCAI_Legal_Acceptances::table(),
            array(
                'participant_id'    => $participant,
                'reservation_id'    => $reservation,
                'order_id'          => 500,
                'document_id'       => 1,
                'document_version'  => $version,
                'document_hash'     => $hash ? $hash : str_repeat( 'a', 64 ),
                'signature_post_id' => $signature_post,
                'acceptance_method' => 'signature',
                'guardian_name'     => $guardian,
                'accepted_at'       => '2026-01-01 10:00:00',
            )
        );
        return (int) $wpdb->insert_id;
    }

    private function rows() {
        global $wpdb;
        return $wpdb->get_results( 'SELECT id, participant_id, reservation_id, document_version FROM ' . WCAI_Legal_Acceptances::table() . ' ORDER BY id ASC' );
    }

    private function unique_key_exists() {
        global $wpdb;
        $found = $wpdb->get_results( 'SHOW INDEX FROM ' . WCAI_Legal_Acceptances::table() . " WHERE Key_name = 'uniq_participant_reservation_version'" );
        return ! empty( $found );
    }

    public function test_upgrade_from_2_21_0_dedupes_keeps_lowest_id_and_preserves_other_rows() {
        global $wpdb;
        $table = WCAI_Legal_Acceptances::table();

        // Estado de uma instalação 2.21.0: sem UNIQUE, com duplicatas.
        if ( $this->unique_key_exists() ) {
            $wpdb->query( "ALTER TABLE $table DROP INDEX uniq_participant_reservation_version" );
        }
        $wpdb->query( "DELETE FROM $table" );

        $keep     = $this->insert_acceptance( 10, 20, 'v1', 'Maria' );
        $dup_a    = $this->insert_acceptance( 10, 20, 'v1' );
        $dup_b    = $this->insert_acceptance( 10, 20, 'v1' );
        $other_v  = $this->insert_acceptance( 10, 20, 'v2' );
        $other_p  = $this->insert_acceptance( 11, 20, 'v1' );
        $null_a   = $this->insert_acceptance( 12, null, 'v1' );
        $null_b   = $this->insert_acceptance( 12, null, 'v1' );

        update_option( 'wcai_version', '2.21.0' );
        wcai_run_migrations();

        $ids = array_map( 'intval', wp_list_pluck( $this->rows(), 'id' ) );
        $this->assertSame( array( $keep, $other_v, $other_p, $null_a, $null_b ), $ids );
        $this->assertNotContains( $dup_a, $ids );
        $this->assertNotContains( $dup_b, $ids );
        $this->assertSame( 'Maria', $wpdb->get_var( $wpdb->prepare( "SELECT guardian_name FROM $table WHERE id = %d", $keep ) ) );
        $this->assertTrue( $this->unique_key_exists(), 'dbDelta deve criar a UNIQUE depois do dedupe.' );
        $this->assertSame( WCAI_VERSION, get_option( 'wcai_version' ) );
    }

    public function test_dedupe_keeps_row_with_signature_and_archives_rows_with_different_hash() {
        global $wpdb;
        $table   = WCAI_Legal_Acceptances::table();
        $archive = $wpdb->prefix . 'wcai_legal_acceptances_archive';

        if ( $this->unique_key_exists() ) {
            $wpdb->query( "ALTER TABLE $table DROP INDEX uniq_participant_reservation_version" );
        }
        $wpdb->query( "DELETE FROM $table" );
        $wpdb->query( "DROP TABLE IF EXISTS $archive" );

        $first_same_hash = $this->insert_acceptance( 30, 40, 'v1' );
        $signed          = $this->insert_acceptance( 30, 40, 'v1', null, null, 99 );
        $other_hash      = $this->insert_acceptance( 30, 40, 'v1', null, str_repeat( 'b', 64 ) );

        update_option( 'wcai_version', '2.21.0' );
        wcai_run_migrations();

        $ids = array_map( 'intval', wp_list_pluck( $this->rows(), 'id' ) );
        $this->assertSame( array( $signed ), $ids, 'Fica a linha com signature_post_id.' );

        $archived = array_map( 'intval', $wpdb->get_col( "SELECT id FROM $archive" ) );
        $this->assertSame( array( $other_hash ), $archived, 'So a linha com hash diferente vai para o arquivo.' );
        $this->assertNotContains( $first_same_hash, $archived );
        $this->assertTrue( $this->unique_key_exists() );
    }

    public function test_current_version_runs_no_step() {
        update_option( 'wcai_version', WCAI_VERSION );
        $before = count( $this->rows() );
        wcai_maybe_upgrade_database();
        $this->assertCount( $before, $this->rows() );
    }

    public function test_fresh_install_runs_every_step_and_sets_version() {
        delete_option( 'wcai_version' );
        wcai_run_migrations();
        $this->assertSame( WCAI_VERSION, get_option( 'wcai_version' ) );
        $this->assertTrue( $this->unique_key_exists() );
    }
}
