<?php
/**
 * Converte um relatorio JUnit do PHPUnit em anotacoes do GitHub Actions (::error).
 * As anotacoes aparecem na API publica de check-runs, que nao exige login (o log do job exige).
 *
 * Uso: php bin/ci-annotate.php /tmp/junit.xml
 */
$file = $argv[1] ?? '';
if ( '' === $file || ! is_file( $file ) ) {
    fwrite( STDERR, "ci-annotate: relatorio nao encontrado\n" );
    exit( 0 );
}

$xml = @simplexml_load_file( $file );
if ( false === $xml ) {
    fwrite( STDERR, "ci-annotate: relatorio JUnit invalido\n" );
    exit( 0 );
}

$escape = static function ( $text ) {
    return str_replace( array( '%', "\r", "\n" ), array( '%25', '%0D', '%0A' ), $text );
};

foreach ( $xml->xpath( '//testcase[failure or error]' ) as $case ) {
    $problem = isset( $case->failure ) ? $case->failure : $case->error;
    $message = trim( (string) $problem );
    $message = substr( preg_replace( '/\s+/', ' ', $message ), 0, 600 );
    $name    = (string) $case['class'] . '::' . (string) $case['name'];
    echo '::error title=' . $escape( $name ) . '::' . $escape( $message ) . "\n";
}
