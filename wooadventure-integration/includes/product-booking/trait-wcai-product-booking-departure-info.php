<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Departure_Info {
    // Legenda das datas: o número é de vagas livres; "precisa de N" só aparece se alguma saída tem grupo mínimo.
    public static function availability_legend( $has_minimum ) {
        $legend = 'Vagas livres em cada data.';

        return $has_minimum ? $legend . ' "precisa de N" é o grupo mínimo para o passeio sair.' : $legend;
    }

    // Textos que o JS usa; um lugar só para o vocabulário (passeio, horário, vagas).
    public static function booking_i18n() {
        return array(
            'forming'      => __( 'Grupo em formação · faltam %d para confirmar', 'wooadventure-integration' ),
            'hintDate'     => __( 'Escolha uma data', 'wooadventure-integration' ),
            'hintTime'     => __( 'Escolha um horário', 'wooadventure-integration' ),
            'hintReady'    => __( 'Pronto: %s', 'wooadventure-integration' ),
            'fetchError'   => __( 'Não foi possível atualizar a disponibilidade. Mostrando os últimos dados carregados; tente novamente.', 'wooadventure-integration' ),
            'timesOne'     => __( '%d horário disponível em %s', 'wooadventure-integration' ),
            'timesMany'    => __( '%d horários disponíveis em %s', 'wooadventure-integration' ),
            'noTimes'      => __( 'Não há passeio neste dia.', 'wooadventure-integration' ),
            'selectedTime' => __( '%s selecionado', 'wooadventure-integration' ),
            'selected'     => __( 'Selecionado: %s', 'wooadventure-integration' ),
            'remaining'    => __( 'Vagas restantes depois da sua reserva: %d', 'wooadventure-integration' ),
            'limit'        => __( 'Máximo %d (vagas restantes)', 'wooadventure-integration' ),
            'childLimit'   => __( 'Máximo de %d por reserva', 'wooadventure-integration' ),
            'closed'       => __( 'Vendas encerradas', 'wooadventure-integration' ),
            'groupRule'    => __( 'O passeio só acontece se o grupo mínimo (%d) for atingido até %s.', 'wooadventure-integration' ),
            'groupRuleAny' => __( 'O passeio só acontece se o grupo mínimo (%d) for atingido.', 'wooadventure-integration' ),
        );
    }

    // 90 -> "1 h 30 min", 120 -> "2 h", 45 -> "45 min".
    public static function duration_label( $minutes ) {
        $minutes = absint( $minutes );

        if ( ! $minutes ) {
            return '';
        }

        $hours = intdiv( $minutes, 60 );
        $rest = $minutes % 60;

        if ( ! $hours ) {
            return $rest . ' min';
        }

        return $rest ? sprintf( '%d h %d min', $hours, $rest ) : $hours . ' h';
    }

    // Valor comum a todos os passeios, ou '' quando varia ou falta em algum (nada de dado inventado).
    private static function uniform_value( array $departures, $key ) {
        $values = array_unique( array_map( static function ( $departure ) use ( $key ) {
            return isset( $departure[ $key ] ) ? (string) $departure[ $key ] : '';
        }, $departures ) );

        $value = reset( $values );

        return 1 === count( $values ) && '' !== $value && '0' !== $value ? $value : '';
    }

    // Só dados reais: cada linha some quando o dado não existe.
    public static function trust_rows( array $departures, $policy, $phone ) {
        $rows = array();
        $duration = self::duration_label( self::uniform_value( $departures, 'duration' ) );
        $meeting = self::uniform_value( $departures, 'meeting_point' );

        if ( $duration ) {
            $rows[] = array( 'key' => 'duration', 'label' => 'Duração', 'value' => $duration );
        }

        if ( $meeting ) {
            $rows[] = array( 'key' => 'meeting', 'label' => 'Ponto de encontro', 'value' => $meeting );
        }

        if ( '' !== trim( (string) $policy ) ) {
            $rows[] = array( 'key' => 'policy', 'label' => 'Cancelamento', 'value' => trim( (string) $policy ) );
        }

        if ( '' !== trim( (string) $phone ) ) {
            $rows[] = array( 'key' => 'phone', 'label' => 'Contato', 'value' => trim( (string) $phone ) );
        }

        return $rows;
    }

    private function render_trust_block( array $rows ) {
        if ( empty( $rows ) ) {
            return;
        }

        echo '<details class="wcai-trust" data-wcai-trust><summary>Informações da reserva</summary><dl>';

        foreach ( $rows as $row ) {
            echo '<div class="is-' . esc_attr( $row['key'] ) . '"><dt>' . esc_html( $row['label'] ) . '</dt><dd>';

            if ( 'phone' === $row['key'] ) {
                echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $row['value'] ) ) . '">' . esc_html( $row['value'] ) . '</a>';
            } else {
                echo nl2br( esc_html( $row['value'] ) );
            }

            echo '</dd></div>';
        }

        echo '</dl></details>';
    }

    private function has_group_minimum( array $departures ) {
        foreach ( $departures as $departure ) {
            if ( ! empty( $departure['minimum'] ) && (int) $departure['minimum'] > 1 ) {
                return true;
            }
        }

        return false;
    }
}
