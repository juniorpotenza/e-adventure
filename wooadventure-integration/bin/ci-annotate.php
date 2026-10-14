<?php
/**
 * Converte um relatorio JUnit do PHPUnit em anotacoes do GitHub Actions (::error).
 * As anotacoes aparecem na API publica de check-runs, que nao exige login (o log do job exige).
 *
 * Uso: php bin/ci-annotate.php /tmp/junit.xml [/tmp/saida-bruta.txt]
 */
$escape = static function ( $text ) {
    return str_replace( array( '%', "\r", "\n" ), array( '%25', '%0D', '%0A' ), $text );
};

$file = $argv[1] ?? '';
$xml  = ( '' !== $file && is_file( $file ) ) ? @simplexml_load_file( $file ) : false;

if ( false === $xml ) {
    echo '::error title=' . $escape( 'PHPUnit: relatorio JUnit ausente ou invalido' ) . "::nenhum teste chegou a gravar o relatorio (erro fatal ou falha de bootstrap?)\n";
} else {
    foreach ( $xml->xpath( '//testcase[failure or error]' ) as $case ) {
        $problem = isset( $case->failure ) ? $case->failure : $case->error;
        $message = substr( preg_replace( '/\s+/', ' ', trim( (string) $problem ) ), 0, 600 );
        $name    = (string) $case['class'] . '::' . (string) $case['name'];
        echo '::error title=' . $escape( $name ) . '::' . $escape( $message ) . "\n";
    }
}

// Cauda da saida bruta do PHPUnit: erros fatais, avisos e falhas de bootstrap nao entram no JUnit.
$output = $argv[2] ?? '';
if ( '' !== $output && is_file( $output ) ) {
    $lines = file( $output, FILE_IGNORE_NEW_LINES );
    $tail  = implode( "\n", array_slice( $lines, -60 ) );
    echo '::error title=' . $escape( 'PHPUnit: ultimas linhas da saida' ) . '::' . $escape( substr( $tail, -6000 ) ) . "\n";
}
