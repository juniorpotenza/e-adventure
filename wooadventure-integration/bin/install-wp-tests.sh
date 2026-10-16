#!/usr/bin/env bash
# Uso: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
# Instala o core do WordPress e a suite de testes em /tmp. Base do script oficial do wp-cli scaffold.
set -euo pipefail

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

# 'latest' vira a versao estavel real: a biblioteca de testes do trunk do SVN e mais nova que o
# nucleo estavel e referencia classes que ele ainda nao tem (ex.: "Interface WP_Secrets_Store not found").
if [[ $WP_VERSION == 'latest' ]]; then
    WP_VERSION=$(curl -fsSL "https://api.wordpress.org/core/version-check/1.7/" | grep -o '"version":"[^"]*' | head -1 | cut -d'"' -f4)
    if [[ -z $WP_VERSION ]]; then
        echo "Nao foi possivel descobrir a versao estavel do WordPress." >&2
        exit 1
    fi
fi
WP_TESTS_TAG="tags/$WP_VERSION"
WP_ARCHIVE="https://wordpress.org/wordpress-$WP_VERSION.tar.gz"
echo "WordPress $WP_VERSION (nucleo e testes na mesma versao)"

if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    curl -fsSL "$WP_ARCHIVE" | tar --strip-components=1 -zxmf - -C "$WP_CORE_DIR"
fi

if [ ! -d "$WP_TESTS_DIR/includes" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn co --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
    svn co --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
fi

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    curl -fsSL "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" > "$WP_TESTS_DIR/wp-tests-config.php"
    WP_CORE_DIR_ESC=$(echo "$WP_CORE_DIR" | sed 's:/\+$::')
    sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_ESC/':" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/youremptytestdbnamehere/$DB_NAME/; s/yourusernamehere/$DB_USER/; s/yourpasswordhere/$DB_PASS/; s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
fi

# WooCommerce para os testes que criam pedidos.
PLUGINS_DIR="$WP_CORE_DIR/wp-content/plugins"
if [ ! -d "$PLUGINS_DIR/woocommerce" ]; then
    mkdir -p "$PLUGINS_DIR"
    curl -fsSL "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip" -o "$TMPDIR/woocommerce.zip"
    unzip -q "$TMPDIR/woocommerce.zip" -d "$PLUGINS_DIR"
fi
