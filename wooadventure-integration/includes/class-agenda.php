<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Agenda {

    public function __construct() {
        // 1. Menus e Scripts
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // 2. AJAX Painel
        add_action( 'wp_ajax_wcai_get_calendar_events', array( $this, 'ajax_get_events' ) );
        add_action( 'wp_ajax_wcai_get_day_details', array( $this, 'ajax_get_day_details' ) );
        add_action( 'wp_ajax_wcai_clear_cache', array( $this, 'ajax_clear_cache' ) );
        
        // 3. Shortcode de Sucesso (MODIFICADO: GERA O TICKET + E-MAIL)
        add_shortcode( 'wcai_confirma_assinatura', array( $this, 'render_success_tracker' ) );

        // 4. Integração iCal (AGRUPADA)
        add_action( 'init', array( $this, 'handle_ical_feed' ), 1 ); 
        
        // Manutenção
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'woocommerce_order_status_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'wcai_reservation_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'wcai_reservations_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'admin_init', array( $this, 'db_auto_repair_column' ) );
        add_action( 'admin_post_wcai_reset_key', array($this, 'admin_reset_key') );
    }

    // =========================================================================
    // PARTE A: ICAL FEED (ORIGINAL AGRUPADO - MANTIDO)
    // =========================================================================

    public function handle_ical_feed() {
        if ( isset( $_GET['wcai_action'] ) && 'ical' === sanitize_key( wp_unslash( $_GET['wcai_action'] ) ) ) {
            $stored_key = trim( (string) get_option( 'wcai_ical_secret_key' ) );
            $request_key = isset( $_GET['key'] ) ? trim( (string) wp_unslash( $_GET['key'] ) ) : '';

            if ( empty( $stored_key ) || empty( $request_key ) || ! hash_equals( $stored_key, $request_key ) ) {
                wp_die( 'Acesso Negado (Chave Inválida)', '403', 403 );
            }

            @set_time_limit( 0 );
            while ( ob_get_level() ) {
                ob_end_clean();
            }

            $debug = isset( $_GET['debug'] );
            $eol   = "\r\n";

            if ( $debug ) {
                header( 'Content-Type: text/html; charset=utf-8' );
                echo '<h1>Agenda iCal operacional</h1><pre>';
            } else {
                header( 'Content-Type: text/calendar; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="agenda_operacional.ics"' );
                header( 'Cache-Control: private, no-store, max-age=0' );
                echo 'BEGIN:VCALENDAR' . $eol;
                echo 'VERSION:2.0' . $eol;
                echo 'PRODID:-//WooAdventure//Operational//PT' . $eol;
                echo 'CALSCALE:GREGORIAN' . $eol;
                echo 'METHOD:PUBLISH' . $eol;
                echo 'X-WR-CALNAME:Agenda Operacional' . $eol;
            }

            $start_req  = wp_date( 'Y-m-d', strtotime( '-2 months' ) );
            $end_req    = wp_date( 'Y-m-d', strtotime( '+18 months' ) );
            $departures = $this->get_canonical_departures_in_range( $start_req, $end_req );
            $timezone   = wp_timezone();
            $utc        = new DateTimeZone( 'UTC' );
            $count      = 0;

            foreach ( $departures as $departure ) {
                $starts_at = get_post_meta( $departure->ID, '_wcai_starts_at', true );
                if ( empty( $starts_at ) ) {
                    continue;
                }

                try {
                    $dt_start = new DateTime( $starts_at, $timezone );
                } catch ( Exception $e ) {
                    continue;
                }

                $duration = absint( get_post_meta( $departure->ID, '_wcai_duration_minutes', true ) );
                $duration = $duration > 0 ? $duration : 180;
                $dt_end   = clone $dt_start;
                $dt_end->modify( '+' . $duration . ' minutes' );

                $reserved = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_reserved_quantity( $departure->ID ) : 0;
                $capacity = absint( get_post_meta( $departure->ID, '_wcai_capacity', true ) );
                $available = max( 0, $capacity - $reserved );
                $status = get_post_meta( $departure->ID, '_wcai_departure_status', true ) ?: 'draft';
                $meeting = get_post_meta( $departure->ID, '_wcai_meeting_point', true );
                $guide_id = absint( get_post_meta( $departure->ID, '_wcai_guide_id', true ) );
                $guide = $guide_id ? get_the_author_meta( 'display_name', $guide_id ) : '';

                $title = get_the_title( $departure->ID ) ?: 'Saída #' . $departure->ID;
                $summary = $title . ' — ' . $reserved . ' pax / ' . $capacity . ' vagas';
                $description = 'Status: ' . $status . "\n" .
                    'Reservados: ' . $reserved . "\n" .
                    'Disponíveis: ' . $available;

                if ( $meeting ) {
                    $description .= "\nPonto de encontro: " . $meeting;
                }
                if ( $guide ) {
                    $description .= "\nGuia: " . $guide;
                }

                $dt_start->setTimezone( $utc );
                $dt_end->setTimezone( $utc );
                $uid = 'departure-' . absint( $departure->ID ) . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

                if ( $debug ) {
                    echo esc_html( $starts_at . ' — ' . $summary ) . "\n";
                } else {
                    echo 'BEGIN:VEVENT' . $eol;
                    echo 'UID:' . $this->ical_escape( $uid ) . $eol;
                    echo 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . $eol;
                    echo 'DTSTART:' . $dt_start->format( 'Ymd\THis\Z' ) . $eol;
                    echo 'DTEND:' . $dt_end->format( 'Ymd\THis\Z' ) . $eol;
                    echo 'SUMMARY:' . $this->ical_escape( $summary ) . $eol;
                    echo 'DESCRIPTION:' . $this->ical_escape( $description ) . $eol;
                    echo 'END:VEVENT' . $eol;
                }

                $count++;
            }

            if ( $debug ) {
                echo "\nTotal de saídas: " . absint( $count ) . '</pre>';
                exit;
            }

            if ( 0 === $count ) {
                echo 'BEGIN:VEVENT' . $eol;
                echo 'UID:empty' . $eol;
                echo 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . $eol;
                echo 'DTSTART:' . gmdate( 'Ymd\THis\Z' ) . $eol;
                echo 'SUMMARY:Sem saídas cadastradas' . $eol;
                echo 'END:VEVENT' . $eol;
            }

            echo 'END:VCALENDAR';
            exit;
        }
    }

    private function get_canonical_departures_in_range( $start, $end ) {
        $start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ? $start : wp_date( 'Y-m-01' );
        $end   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ? $end : wp_date( 'Y-m-t' );

        $departures = get_posts( array(
            'post_type'      => WCAI_Departures::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_wcai_starts_at',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_wcai_starts_at',
                    'value'   => array( $start . 'T00:00', $end . 'T23:59' ),
                    'compare' => 'BETWEEN',
                    'type'    => 'CHAR',
                ),
            ),
        ) );

        return array_values( array_filter( $departures, static function( $departure ) {
            $status = get_post_meta( $departure->ID, '_wcai_departure_status', true );
            return 'draft' !== $status;
        } ) );
    }

    private function ical_escape( $value ) {
        $value = str_replace( "\\", "\\\\", (string) $value );
        $value = str_replace( array( "\r\n", "\r", "\n" ), "\\n", $value );
        return str_replace( array( ';', ',' ), array( "\\;", "\\," ), $value );
    }

    // =========================================================================
    // PARTE B: PAINEL ADMIN (MANTIDO)
    // =========================================================================

    public function admin_reset_key() {
        if ( ! current_user_can( WCAI_Capabilities::MANAGE_DEPARTURES ) ) {
            wp_die( 'Acesso negado.', 403 );
        }

        check_admin_referer( 'wcai_reset_ical_key' );
        update_option('wcai_ical_secret_key', wp_generate_password(24, false));
        wp_safe_redirect(admin_url('admin.php?page=wcai-agenda'));
        exit;
    }

    public function add_menu_page() { 
        add_submenu_page('woocommerce', 'Agenda', 'Agenda Passeios', WCAI_Capabilities::VIEW_MANIFEST, 'wcai-agenda', array($this, 'render_page')); 
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wcai-agenda' ) === false ) return;
        wp_enqueue_style( 'fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' );
        wp_enqueue_script( 'fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array(), '5.11.3', true );
        wp_enqueue_script( 'fullcalendar-locales', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js', array('fullcalendar-js'), '5.11.3', true );
        wp_add_inline_style( 'fullcalendar-css', ".wcai-calendar-wrapper { background:#fff; padding:20px; margin-top:20px; border-radius:5px; box-shadow:0 1px 3px rgba(0,0,0,0.1); } .wcai-sync-box { background:#fff; padding:15px; border:1px solid #ccd0d4; border-left:4px solid #007cba; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 1px rgba(0,0,0,.04); } .wcai-sync-input { width:60%; padding:8px; background:#f0f0f1; border:1px solid #8c8f94; color:#50575e; } .wcai-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(2px); } .wcai-modal-content { background:#fff; margin:5% auto; width:95%; max-width:900px; padding:0; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3); max-height:85vh; overflow-y:auto; } .wcai-modal-header { padding:15px 20px; background:#f8f9fa; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; } .wcai-modal-body { padding:20px; } .wcai-close { font-size:28px; cursor:pointer; color:#aaa; } .wcai-table { width:100%; border-collapse:collapse; margin-top:10px; } .wcai-table th { text-align:left; padding:10px; background:#f1f1f1; border-bottom:2px solid #ddd; font-size:13px; } .wcai-table td { padding:10px; border-bottom:1px solid #eee; } .wcai-status { padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700; text-transform:uppercase; } .status-completed { background:#d4edda; color:#155724; } .status-processing { background:#cce5ff; color:#004085; } .fc-event { cursor:pointer; border:none; margin-bottom:2px!important; }");
    }

    public function render_page() {
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_die( 'Acesso negado.', 403 );
        }
        $key = get_option('wcai_ical_secret_key') ?: wp_generate_password(24, false); update_option('wcai_ical_secret_key', $key);
        $feed_url = site_url('/?wcai_action=ical&key=' . $key);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Agenda de Passeios</h1>
            <hr class="wp-header-end">
            <div class="wcai-sync-box">
                <div style="flex-grow:1; margin-right:15px;">
                    <strong>🔗 Sincronização Automática:</strong><br>
                    <input type="text" class="wcai-sync-input" value="<?php echo esc_attr( $feed_url ); ?>" style="width:100%" readonly onclick="this.select()">
                </div>
                <div>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcai_reset_key'), 'wcai_reset_ical_key' ) ); ?>" class="button" onclick="return confirm('Isso invalida o link anterior. Tem certeza?');">🔄 Gerar Nova Chave</a>
                    <button type="button" id="wcai-btn-clear-cache" class="button button-secondary">🧹 Limpar Cache</button>
                </div>
            </div>
            <div class="wcai-calendar-wrapper"><div id="wcai-calendar"></div></div>
            <div id="wcaiDetailModal" class="wcai-modal"><div class="wcai-modal-content"><div class="wcai-modal-header"><h2 style="margin:0" id="modalTitle">Detalhes</h2><span class="wcai-close">&times;</span></div><div class="wcai-modal-body" id="modalBody"></div></div></div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendar = new FullCalendar.Calendar(document.getElementById('wcai-calendar'), {
                initialView: 'dayGridMonth', locale: 'pt-br', height: 'auto', displayEventTime: true,
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false, hour12: false },
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
                events: { url: '<?php echo admin_url('admin-ajax.php'); ?>', method: 'POST', extraParams: { action: 'wcai_get_calendar_events', nonce: '<?php echo wp_create_nonce('wcai_calendar_nonce'); ?>' } },
                eventClick: function(info) {
                    var m = document.getElementById("wcaiDetailModal"); m.style.display = "block";
                    document.getElementById("modalBody").innerHTML = '<div style="text-align:center;padding:20px">Carregando...</div>';
                    var rawStr = info.event.startStr; var parts = rawStr.split('T');
                    var title = parts[0].split('-').reverse().join('/'); if(parts[1] && !info.event.allDay) title += ' às ' + parts[1].substring(0, 5);
                    document.getElementById("modalTitle").innerText = title;
                    jQuery.post(ajaxurl, { action: 'wcai_get_day_details', departure_id: info.event.id, nonce: '<?php echo wp_create_nonce('wcai_calendar_nonce'); ?>' })
                    .done(function(r){ document.getElementById("modalBody").innerHTML = r.success ? r.data.html : '<p>Erro.</p>'; });
                }
            });
            calendar.render();
            jQuery('#wcai-btn-clear-cache').click(function(e){ e.preventDefault(); jQuery(this).text('Limpando...').prop('disabled', true); jQuery.post(ajaxurl, { action: 'wcai_clear_cache', nonce: '<?php echo esc_js( wp_create_nonce( 'wcai_clear_calendar_cache' ) ); ?>' }, function(){ location.reload(); }); });
            jQuery('.wcai-close').click(function(){ jQuery('#wcaiDetailModal').fadeOut(); });
            jQuery(window).click(function(e){ if(e.target.id=='wcaiDetailModal') jQuery('#wcaiDetailModal').fadeOut(); });
        });
        </script>
        <?php
    }

    public function ajax_get_events() {
        check_ajax_referer( 'wcai_calendar_nonce', 'nonce' );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }

        $start = isset( $_POST['start'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['start'] ) ), 0, 10 ) : wp_date( 'Y-m-01' );
        $end   = isset( $_POST['end'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['end'] ) ), 0, 10 ) : wp_date( 'Y-m-t' );
        $cache_key = 'wcai_events_' . md5( $start . '|' . $end );
        $cached = get_transient( $cache_key );

        if ( false !== $cached ) {
            wp_send_json( $cached );
        }

        $events = array();
        $departures = $this->get_canonical_departures_in_range( $start, $end );

        foreach ( $departures as $departure ) {
            $departure_id = absint( $departure->ID );
            $starts_at = get_post_meta( $departure_id, '_wcai_starts_at', true );
            if ( empty( $starts_at ) ) {
                continue;
            }

            $status = get_post_meta( $departure_id, '_wcai_departure_status', true ) ?: 'draft';
            $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
            $reserved = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_reserved_quantity( $departure_id ) : 0;
            $available = max( 0, $capacity - $reserved );
            $title = get_the_title( $departure_id ) ?: 'Saída #' . $departure_id;

            $status_labels = array(
                'open' => 'Aberta',
                'full' => 'Lotada',
                'confirmed' => 'Confirmada',
                'cancelled' => 'Cancelada',
                'completed' => 'Concluída',
            );
            $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
            $event_title = $title . ' — ' . $reserved . '/' . $capacity . ' pax';

            if ( 'cancelled' === $status ) {
                $event_title .= ' · CANCELADA';
            }

            $background = 'open' === $status ? '#3788d8' : '#6c757d';
            if ( 'confirmed' === $status ) {
                $background = '#155724';
            } elseif ( 'full' === $status ) {
                $background = '#d69e2e';
            } elseif ( 'cancelled' === $status ) {
                $background = '#dc3545';
            } elseif ( 'completed' === $status ) {
                $background = '#6c757d';
            }

            $events[] = array(
                'id' => (string) $departure_id,
                'title' => $event_title,
                'start' => $starts_at,
                'allDay' => false,
                'backgroundColor' => $background,
                'borderColor' => $background,
                'extendedProps' => array(
                    'departure_id' => $departure_id,
                    'status' => $status,
                    'status_label' => $status_label,
                    'capacity' => $capacity,
                    'reserved' => $reserved,
                    'available' => $available,
                ),
            );
        }

        usort( $events, static function( $a, $b ) {
            return strcmp( $a['start'], $b['start'] );
        } );

        set_transient( $cache_key, $events, 15 * MINUTE_IN_SECONDS );
        wp_send_json( $events );
    }

    public function ajax_get_day_details() {
        check_ajax_referer( 'wcai_calendar_nonce', 'nonce' );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }

        $departure_id = isset( $_POST['departure_id'] ) ? absint( $_POST['departure_id'] ) : 0;
        $departure = $departure_id ? get_post( $departure_id ) : false;

        if ( ! $departure || WCAI_Departures::POST_TYPE !== $departure->post_type || 'publish' !== $departure->post_status ) {
            wp_send_json_error( array( 'message' => 'Saída não encontrada.' ), 404 );
        }

        $starts_at = get_post_meta( $departure_id, '_wcai_starts_at', true );
        $status = get_post_meta( $departure_id, '_wcai_departure_status', true ) ?: 'draft';
        $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
        $reserved = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_reserved_quantity( $departure_id ) : 0;
        $available = max( 0, $capacity - $reserved );

        global $wpdb;
        $participants_table = WCAI_Participants_DB::get_table_name();
        $reservations_table = WCAI_Reservations::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, r.status AS reservation_status
                 FROM $participants_table p
                 INNER JOIN $reservations_table r ON r.id = p.reservation_id
                 WHERE r.departure_id = %d
                   AND r.status IN ('pending', 'confirmed')
                 ORDER BY p.nome_completo ASC, p.id ASC",
                $departure_id
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            wp_send_json_success(
                array(
                    'html' => '<p>Nenhum participante vinculado a esta saída.</p>',
                )
            );
        }

        $can_view_sensitive = current_user_can( WCAI_Capabilities::VIEW_SENSITIVE );
        $status_labels = array(
            'pending' => 'Pendente',
            'confirmed' => 'Confirmada',
        );

        ob_start();
        ?>
        <div style="margin-bottom:15px;background:#eef2f7;padding:10px;border-radius:8px;">
            <strong><?php echo esc_html( get_the_title( $departure_id ) ?: 'Saída #' . $departure_id ); ?></strong><br>
            <span><?php echo esc_html( $starts_at ); ?></span> ·
            <span><?php echo esc_html( $reserved . '/' . $capacity . ' pax' ); ?></span> ·
            <span><?php echo esc_html( $available . ' vagas disponíveis' ); ?></span> ·
            <span><?php echo esc_html( ucfirst( $status ) ); ?></span>
        </div>
        <table class="wcai-table">
            <thead>
                <tr>
                    <th style="width:30px">T.</th>
                    <th>Pedido</th>
                    <th>Participante</th>
                    <?php if ( $can_view_sensitive ) : ?><th>CPF</th><th>Nascimento</th><?php endif; ?>
                    <th>Reserva</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                $order_number = $row['order_id'] ? wc_get_order( $row['order_id'] ) : false;
                $order_label = $order_number ? $order_number->get_order_number() : $row['order_id'];
                $reservation_label = isset( $status_labels[ $row['reservation_status'] ] ) ? $status_labels[ $row['reservation_status'] ] : $row['reservation_status'];
                $nasc = $row['data_nascimento'];
                if ( $can_view_sensitive && ! empty( $nasc ) && '0000-00-00' !== $nasc ) {
                    $date = DateTime::createFromFormat( 'Y-m-d', $nasc );
                    if ( $date ) {
                        $nasc = $date->format( 'd/m/Y' );
                    }
                }
                $signed = ! empty( $row['termo_assinado'] );
            ?>
                <tr>
                    <td style="text-align:center;"><?php echo $signed ? '<span title="Assinado">✅</span>' : '<span title="Pendente" style="opacity:0.3">⚠️</span>'; ?></td>
                    <td>
                        <?php if ( $row['order_id'] ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $row['order_id'] ) ); ?>" target="_blank">#<?php echo esc_html( $order_label ); ?></a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $row['nome_completo'] ); ?></td>
                    <?php if ( $can_view_sensitive ) : ?>
                        <td><?php echo esc_html( $row['cpf'] ); ?></td>
                        <td><?php echo esc_html( $nasc ); ?></td>
                    <?php endif; ?>
                    <td><?php echo esc_html( $reservation_label ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:15px;text-align:right"><button class="button button-primary" onclick="window.print()">🖨️ Imprimir</button></div>
        <?php
        wp_send_json_success( array( 'html' => ob_get_clean() ) );
    }

    // =========================================================================
    // MÉTODOS DE RASTREAMENTO (MODIFICADO: Ticket + Check-in + E-mail)
    // =========================================================================
    public function render_success_tracker() {
        if ( ! class_exists( 'WCAI_Assinatura' ) ) {
            return '';
        }

        $ticket_info = WCAI_Assinatura::consume_ticket_session();

        if ( ! $ticket_info || empty( $ticket_info['qr_url'] ) ) {
            return '';
        }

        $this->send_ticket_email_via_shortcode( $ticket_info );

        return '
        <div style="text-align:center; padding:20px; background:#fff; border:1px solid #d4edda; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-bottom:20px;">
            <div style="color:#155724; font-size:18px; font-weight:bold; margin-bottom:15px;">✅ Assinatura Confirmada!</div>
            <p>O seu ingresso foi enviado por e-mail. Também pode guardá-lo agora:</p>
            <div style="margin:20px auto; display:inline-block; border:1px solid #ccc; padding:10px; background:#fff;">
                <img src="' . esc_url( $ticket_info['qr_url'] ) . '" alt="QR Code Ticket" style="width:200px; height:200px;">
            </div>
            <div style="font-size:12px; color:#777; margin-top:10px;">
                Participante: <strong>' . esc_html( $ticket_info['nome'] ) . '</strong><br>
                Pedido: #' . esc_html( $ticket_info['order_id'] ) . '
            </div>
            <button onclick="window.print()" style="margin-top:15px; padding:10px 20px; background:#007cba; color:#fff; border:none; border-radius:4px; cursor:pointer;">🖨️ Imprimir / Salvar</button>
        </div>';
    }

    // --- NOVA FUNÇÃO DE DISPARO DE E-MAIL ---
    private function send_ticket_email_via_shortcode($ticket_info) {
        $order_id = $ticket_info['order_id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('[WCAI] Erro Email: Pedido não carregou.');
            return;
        }

        // 1. TENTA LER O COOKIE DO E-MAIL CAPTURADO
        $to = '';
        if ( isset($_COOKIE['wcai_pax_email_temp']) && is_email($_COOKIE['wcai_pax_email_temp']) ) {
            $to = sanitize_email($_COOKIE['wcai_pax_email_temp']);
            error_log('[WCAI] Usando e-mail capturado do cookie: ' . $to);
            // Limpa o cookie do email para não ficar "sujo"
            setcookie('wcai_pax_email_temp', '', time() - 3600, '/');
        } else {
            // 2. FALLBACK: Usa o Billing Email
            $to = $order->get_billing_email();
            error_log('[WCAI] E-mail capturado não encontrado. Usando Billing: ' . $to);
        }

        if ( empty($to) ) {
            error_log('[WCAI] Erro Fatal: Nenhum destinatário para o ingresso.');
            return;
        }

        $nome_pax = $ticket_info['nome'];
        $qr_img = $ticket_info['qr_url'];
        $subject = "🎟️ Seu Ingresso - Pedido #$order_id";
        
        $admin_email = get_option('admin_email');
        $site_title = get_bloginfo('name');
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $site_title <$admin_email>"
        );

        $msg = "
        <html>
        <body style='font-family:Arial, sans-serif; color:#333; background-color:#f4f4f4; padding:20px;'>
            <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #ddd; padding:20px; border-radius:10px;'>
                <h2 style='color:#007cba; text-align:center;'>Passeio Confirmado!</h2>
                <p>Olá,</p>
                <p>O termo de responsabilidade de <strong>$nome_pax</strong> foi assinado com sucesso.</p>
                <p>Abaixo está o ingresso digital para entrada no parque.</p>
                
                <div style='text-align:center; margin:30px 0; background:#f9f9f9; padding:20px; border-radius:10px; border:1px dashed #ccc;'>
                    <img src='$qr_img' alt='QR Code Ticket' style='width:200px; height:200px;'><br>
                    <strong style='font-size:24px; letter-spacing:2px; display:block; margin-top:15px; color:#333;'>#$order_id</strong>
                    <p style='font-size:14px; color:#666; margin-top:5px;'>Apresente este código na portaria.</p>
                </div>

                <p style='text-align:center; color:#555;'>Dica: <strong>Guarde esta imagem</strong> no seu telemóvel.</p>
                <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                <p style='font-size:12px; color:#aaa; text-align:center;'>$site_title - Ecoturismo</p>
            </div>
        </body>
        </html>
        ";

        $sent = wp_mail($to, $subject, $msg, $headers);
        if($sent) error_log('[WCAI] Sucesso no envio do e-mail para: ' . $to);
        else error_log('[WCAI] Falha no wp_mail.');
    }

    public function ajax_clear_cache() {
        check_ajax_referer( 'wcai_clear_calendar_cache', 'nonce' );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }

        $this->clear_calendar_cache_internal();
        wp_send_json_success();
    }
    public function db_auto_repair_column() {}
    public function clear_calendar_cache_internal() {
        global $wpdb;
        $option_names = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_wcai_events_%'" );
        foreach ( $option_names as $option_name ) {
            $transient_name = substr( $option_name, strlen( '_transient_' ) );
            if ( $transient_name !== '' ) {
                delete_transient( $transient_name );
            }
        }
    } 
}
