<?php
declare(strict_types=1);

// ============================================================================
// Part 1: Constants
// ============================================================================

define('INSTALL_VERSION', '1.0.0');
define('SUPPORTED_DB_DRIVER', 'mysql');
define('ENV_FILE_PATH', dirname(__DIR__) . '/api/.env');
define('ENV_LOCAL_PATH', dirname(__DIR__) . '/api/.env.local');
define('ENV_EXAMPLE_PATH', dirname(__DIR__) . '/api/.env.example');
define('LOCK_FILE_PATH', dirname(__DIR__) . '/api/.install.lock');
define('STORAGE_BASE_DEFAULT', dirname(__DIR__) . '/storage_api');
define('MYSQL_SCHEMA_SNAPSHOT_PATH', __DIR__ . '/install/mysql-schema.snapshot.sql');
define('UPDATE_CENTER_URL', 'https://update.tropatt.com');
define('UPDATE_PRODUCT', 'tropatt-core');
define('UPDATE_CHANNEL', 'stable');

function hasEnvConfig(): bool
{
    return is_file(ENV_FILE_PATH) || is_file(ENV_LOCAL_PATH);
}

function getEnvConfigPath(): string
{
    if (is_file(ENV_LOCAL_PATH)) return ENV_LOCAL_PATH;
    return ENV_FILE_PATH;
}

// ============================================================================
// Part 2: Session & CSRF
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    // Set session storage to project-controlled directory (shared hosting security)
    $sessionDir = dirname(__DIR__) . '/storage_api/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0700, true);
    }
    ini_set('session.save_path', $sessionDir);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    // SEC-021: Set Secure flag when running over HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// Part 3: Language Detection & Definitions
// ============================================================================

function detectLanguage(): string
{
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['ru', 'en', 'zh', 'es', 'pt', 'de', 'fr'], true)) {
        return $_SESSION['lang'];
    }

    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (preg_match('/^[a-z]{2}/i', $accept, $m)) {
        $primary = strtolower($m[0]);
        if ($primary === 'ru') return 'ru';
        if ($primary === 'zh') return 'zh';
        if ($primary === 'es') return 'es';
        if ($primary === 'pt') return 'pt';
        if ($primary === 'de') return 'de';
        if ($primary === 'fr') return 'fr';
    }
    return 'en';
}

$lang = detectLanguage();

$L = [];

// Russian
$L['ru'] = [
    'title' => 'Установка CRM',
    'already_installed' => 'Система уже установлена',
    'already_installed_desc' => 'Система уже установлена. Для переустановки удалите конфигурационный файл и файл блокировки установки из папки api/.',
    'go_to_dashboard' => 'Перейти в панель управления',
    'step' => 'Шаг',
    'step_db' => 'База данных',
    'step_site' => 'Настройки сайта',
    'step_keys' => 'Ключи безопасности',
    'step_install' => 'Установка',
    'driver' => 'Драйвер',
    'mysql_only_note' => 'Установщик использует MySQL — это основной и поддерживаемый режим CRM.',
    'requirements' => 'Проверка окружения',
    'requirement_ok' => 'Готово',
    'requirement_fail' => 'Требует внимания',
    'host' => 'Хост',
    'port' => 'Порт',
    'database' => 'База данных',
    'username' => 'Пользователь',
    'password' => 'Пароль',
    'sqlite_path' => 'Путь к файлу SQLite',
    'test_connection' => 'Проверить подключение',
    'testing' => 'Проверка...',
    'connection_ok' => 'Подключение успешно',
    'connection_fail' => 'Ошибка подключения',
    'next' => 'Далее',
    'back' => 'Назад',
    'site_url' => 'URL сайта',
    'timezone' => 'Часовой пояс',
    'admin_login' => 'Логин',
    'admin_email' => 'Email администратора',
    'admin_password' => 'Пароль администратора',
    'admin_password_confirm' => 'Подтверждение пароля',
    'admin_name' => 'Имя администратора',
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => 'Сгенерировать все',
    'summary' => 'Сводка настроек',
    'summary_driver' => 'Драйвер БД',
    'summary_db_name' => 'Имя БД',
    'summary_site_url' => 'URL сайта',
    'summary_timezone' => 'Часовой пояс',
    'summary_admin' => 'Администратор',
    'install_now' => 'Установить',
    'installing' => 'Идёт установка...',
    'step_write_env' => 'Запись .env файла',
    'step_create_tables' => 'Создание таблиц БД',
    'step_seed_data' => 'Заполнение справочников',
    'step_create_admin' => 'Создание администратора',
    'step_demo_data' => 'Демо-данные',
    'step_finalize' => 'Финализация',
    'install_success' => 'Установка завершена!',
    'install_success_desc' => 'Система успешно установлена. Используйте данные ниже для входа.',
    'recovery_key_title' => 'Ключ восстановления',
    'recovery_key_desc' => 'Сохраните его сейчас — он понадобится для аварийного доступа к /updater/rescue.php, если обновление оставит CRM в режиме обслуживания. Ключ показывается только один раз.',
    'recovery_key_value' => 'Ключ',
    'login_credentials' => 'Данные для входа',
    'url' => 'URL',
    'login_label' => 'Логин',
    'required_field' => 'Обязательное поле',
    'passwords_mismatch' => 'Пароли не совпадают',
    'password_min_length' => 'Минимум 12 символов (заглавные, строчные, цифры, спецсимволы)',
    'invalid_email' => 'Некорректный email',
    'invalid_url' => 'Некорректный URL',
    'show' => 'Показать',
    'hide' => 'Скрыть',
    'error_occurred' => 'Произошла ошибка',
    'db_connect_error' => 'Не удалось подключиться к БД',
    'env_write_error' => 'Ошибка записи .env файла',
    'table_create_error' => 'Ошибка создания таблиц',
    'lock_file_error' => 'Система уже установлена. Если нужно переустановить — удалите api/.env, api/.install.lock и storage_api/install.lock.',
    'install' => 'Установка',
    'optional' => 'опционально',
    'update_check_notice' => 'Установщик проверит доступность обновлений и передаст домен этой установки на сервер обновлений.',
    'update_available_after_install' => 'Доступна более новая версия %s. После установки зайдите в раздел обновлений и обновите систему.',
    'confirm_install' => 'Начать установку CRM? Убедитесь, что все настройки верны.',
    'network_error' => 'Ошибка сети',
    'install_failed' => 'Установка не выполнена. Проверьте журнал сервера.',
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'Доступна запись в конфигурацию API',
    'preflight_storage_writable' => 'Доступна запись в хранилище',
    'preflight_session' => 'Сессия',
    'preflight_file_info' => 'Fileinfo (finfo)',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS (dns_get_record)',
];

// English
$L['en'] = [
    'title' => 'CRM Installation',
    'already_installed' => 'System already installed',
    'already_installed_desc' => 'System is already installed. To reinstall, remove the configuration file and the installer lock file from the api/ directory.',
    'go_to_dashboard' => 'Go to Dashboard',
    'step' => 'Step',
    'step_db' => 'Database',
    'step_site' => 'Site Settings',
    'step_keys' => 'Security Keys',
    'step_install' => 'Install',
    'driver' => 'Driver',
    'mysql_only_note' => 'The installer uses MySQL — the primary supported CRM mode.',
    'requirements' => 'Environment check',
    'requirement_ok' => 'Ready',
    'requirement_fail' => 'Needs attention',
    'host' => 'Host',
    'port' => 'Port',
    'database' => 'Database',
    'username' => 'Username',
    'password' => 'Password',
    'sqlite_path' => 'SQLite File Path',
    'test_connection' => 'Test Connection',
    'testing' => 'Testing...',
    'connection_ok' => 'Connection successful',
    'connection_fail' => 'Connection failed',
    'next' => 'Next',
    'back' => 'Back',
    'site_url' => 'Site URL',
    'timezone' => 'Timezone',
    'admin_login' => 'Login',
    'admin_email' => 'Admin Email',
    'admin_password' => 'Admin Password',
    'admin_password_confirm' => 'Confirm Password',
    'admin_name' => 'Admin Name',
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => 'Regenerate All',
    'summary' => 'Settings Summary',
    'summary_driver' => 'DB Driver',
    'summary_db_name' => 'DB Name',
    'summary_site_url' => 'Site URL',
    'summary_timezone' => 'Timezone',
    'summary_admin' => 'Administrator',
    'install_now' => 'Install',
    'installing' => 'Installing...',
    'step_write_env' => 'Writing .env file',
    'step_create_tables' => 'Creating database tables',
    'step_seed_data' => 'Seeding reference data',
    'step_create_admin' => 'Creating admin user',
    'step_demo_data' => 'Creating demo data',
    'step_finalize' => 'Finalizing',
    'install_success' => 'Installation complete!',
    'install_success_desc' => 'The system has been installed successfully. Use the credentials below to log in.',
    'recovery_key_title' => 'Recovery key',
    'recovery_key_desc' => 'Save it now - it is needed for emergency access to /updater/rescue.php if an update leaves the CRM in maintenance mode. The key is shown only once.',
    'recovery_key_value' => 'Key',
    'login_credentials' => 'Login Credentials',
    'url' => 'URL',
    'login_label' => 'Login',
    'required_field' => 'Required field',
    'passwords_mismatch' => 'Passwords do not match',
    'password_min_length' => 'Minimum 12 characters (uppercase, lowercase, digits, special)',
    'invalid_email' => 'Invalid email',
    'invalid_url' => 'Invalid URL',
    'show' => 'Show',
    'hide' => 'Hide',
    'error_occurred' => 'An error occurred',
    'db_connect_error' => 'Could not connect to database',
    'env_write_error' => 'Error writing .env file',
    'table_create_error' => 'Error creating tables',
    'lock_file_error' => 'System already installed. To reinstall, delete api/.env, api/.install.lock, and storage_api/install.lock.',
    'install' => 'Installation',
    'optional' => 'optional',
    'update_check_notice' => 'The installer will check update availability and send this installation domain to the update server.',
    'update_available_after_install' => 'A newer version %s is available. After installation, open Updates and update the system.',
    'confirm_install' => 'Start CRM installation? Make sure all settings are correct.',
    'network_error' => 'Network error',
    'install_failed' => 'Installation failed. Check server logs for details.',
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'API config writable',
    'preflight_storage_writable' => 'Storage writable',
    'preflight_session' => 'Session',
    'preflight_file_info' => 'File info (finfo)',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS (dns_get_record)',
];

// Chinese
$L['zh'] = [
    'title' => 'CRM 安装',
    'already_installed' => '系统已安装',
    'already_installed_desc' => '系统已安装。如需重新安装，请删除 api/ 目录中的配置文件和安装锁定文件。',
    'go_to_dashboard' => '进入控制面板',
    'step' => '步骤',
    'step_db' => '数据库',
    'step_site' => '站点设置',
    'step_keys' => '安全密钥',
    'step_install' => '安装',
    'driver' => '驱动',
    'mysql_only_note' => '安装程序使用 MySQL — CRM 的主要支持模式。',
    'requirements' => '环境检查',
    'requirement_ok' => '就绪',
    'requirement_fail' => '需要处理',
    'host' => '主机',
    'port' => '端口',
    'database' => '数据库',
    'username' => '用户名',
    'password' => '密码',
    'sqlite_path' => 'SQLite 文件路径',
    'test_connection' => '测试连接',
    'testing' => '测试中...',
    'connection_ok' => '连接成功',
    'connection_fail' => '连接失败',
    'next' => '下一步',
    'back' => '返回',
    'site_url' => '网站 URL',
    'timezone' => '时区',
    'admin_login' => '登录名',
    'admin_email' => '管理员邮箱',
    'admin_password' => '管理员密码',
    'admin_password_confirm' => '确认密码',
    'admin_name' => '管理员名称',
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => '重新生成所有',
    'summary' => '设置摘要',
    'summary_driver' => '数据库驱动',
    'summary_db_name' => '数据库名称',
    'summary_site_url' => '网站 URL',
    'summary_timezone' => '时区',
    'summary_admin' => '管理员',
    'install_now' => '开始安装',
    'installing' => '安装中...',
    'step_write_env' => '写入 .env 文件',
    'step_create_tables' => '创建数据库表',
    'step_seed_data' => '填充参考数据',
    'step_create_admin' => '创建管理员用户',
    'step_demo_data' => '创建演示数据',
    'step_finalize' => '完成安装',
    'install_success' => '安装完成！',
    'install_success_desc' => '系统安装成功。请使用下方凭据登录。',
    'recovery_key_title' => '恢复密钥',
    'recovery_key_desc' => '请立即保存——如果更新使 CRM 处于维护模式，需要通过 /updater/rescue.php 进行紧急访问。该密钥仅显示一次。',
    'recovery_key_value' => '密钥',
    'login_credentials' => '登录凭据',
    'url' => 'URL',
    'login_label' => '用户名',
    'required_field' => '必填字段',
    'passwords_mismatch' => '密码不匹配',
    'password_min_length' => '至少 12 个字符（大写、小写、数字、特殊字符）',
    'invalid_email' => '无效的邮箱',
    'invalid_url' => '无效的 URL',
    'show' => '显示',
    'hide' => '隐藏',
    'error_occurred' => '发生错误',
    'db_connect_error' => '无法连接数据库',
    'env_write_error' => '写入 .env 文件失败',
    'table_create_error' => '创建表失败',
    'lock_file_error' => '系统已安装。如需重新安装，请删除 api/.env、api/.install.lock 和 storage_api/install.lock。',
    'install' => '安装',
    'optional' => '可选',
    'update_check_notice' => '安装程序将检查更新可用性，并将此安装的域名发送到更新服务器。',
    'update_available_after_install' => '有更新版本 %s。安装完成后，请打开更新部分并更新系统。',
    'confirm_install' => '开始安装 CRM？请确保所有设置正确。',
    'network_error' => '网络错误',
    'install_failed' => '安装失败。请检查服务器日志。',
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'API 配置可写',
    'preflight_storage_writable' => '存储可写',
    'preflight_session' => '会话',
    'preflight_file_info' => 'Fileinfo（finfo）',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS（dns_get_record）',
];

// Spanish
$L['es'] = [
    'title' => 'Instalación de CRM',
    'already_installed' => 'Sistema ya instalado',
    'already_installed_desc' => 'El sistema ya está instalado. Para reinstalar, elimine el archivo de configuración y el archivo de bloqueo del instalador del directorio api/.',
    'go_to_dashboard' => 'Ir al Panel de Control',
    'step' => 'Paso',
    'step_db' => 'Base de Datos',
    'step_site' => 'Configuración del Sitio',
    'step_keys' => 'Claves de Seguridad',
    'step_install' => 'Instalar',
    'driver' => 'Controlador',
    'mysql_only_note' => 'El instalador usa MySQL — el modo principal y compatible con CRM.',
    'requirements' => 'Verificación del entorno',
    'requirement_ok' => 'Listo',
    'requirement_fail' => 'Requiere atención',
    'host' => 'Host',
    'port' => 'Puerto',
    'database' => 'Base de datos',
    'username' => 'Usuario',
    'password' => 'Contraseña',
    'sqlite_path' => 'Ruta del archivo SQLite',
    'test_connection' => 'Probar conexión',
    'testing' => 'Probando...',
    'connection_ok' => 'Conexión exitosa',
    'connection_fail' => 'Error de conexión',
    'next' => 'Siguiente',
    'back' => 'Atrás',
    'site_url' => 'URL del sitio',
    'timezone' => 'Zona horaria',
    'admin_login' => 'Usuario',
    'admin_email' => 'Email del administrador',
    'admin_password' => 'Contraseña del administrador',
    'admin_password_confirm' => 'Confirmar contraseña',
    'admin_name' => 'Nombre del administrador',
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => 'Regenerar todo',
    'summary' => 'Resumen de configuración',
    'summary_driver' => 'Controlador BD',
    'summary_db_name' => 'Nombre BD',
    'summary_site_url' => 'URL del sitio',
    'summary_timezone' => 'Zona horaria',
    'summary_admin' => 'Administrador',
    'install_now' => 'Instalar',
    'installing' => 'Instalando...',
    'step_write_env' => 'Escribiendo archivo .env',
    'step_create_tables' => 'Creando tablas de la base de datos',
    'step_seed_data' => 'Llenando datos de referencia',
    'step_create_admin' => 'Creando usuario administrador',
    'step_demo_data' => 'Creando datos de demostración',
    'step_finalize' => 'Finalizando',
    'install_success' => '¡Instalación completada!',
    'install_success_desc' => 'El sistema se ha instalado correctamente. Use las credenciales a continuación para iniciar sesión.',
    'recovery_key_title' => 'Clave de recuperación',
    'recovery_key_desc' => 'Guárdela ahora: es necesaria para el acceso de emergencia a /updater/rescue.php si una actualización deja la CRM en modo mantenimiento. La clave se muestra solo una vez.',
    'recovery_key_value' => 'Clave',
    'login_credentials' => 'Credenciales de acceso',
    'url' => 'URL',
    'login_label' => 'Usuario',
    'required_field' => 'Campo obligatorio',
    'passwords_mismatch' => 'Las contraseñas no coinciden',
    'password_min_length' => 'Mínimo 12 caracteres (mayúsculas, minúsculas, números, especiales)',
    'invalid_email' => 'Email inválido',
    'invalid_url' => 'URL inválida',
    'show' => 'Mostrar',
    'hide' => 'Ocultar',
    'error_occurred' => 'Ocurrió un error',
    'db_connect_error' => 'No se pudo conectar a la base de datos',
    'env_write_error' => 'Error al escribir el archivo .env',
    'table_create_error' => 'Error al crear las tablas',
    'lock_file_error' => 'Sistema ya instalado. Para reinstalar, elimine api/.env, api/.install.lock y storage_api/install.lock.',
    'install' => 'Instalación',
    'optional' => 'opcional',
    'update_check_notice' => 'El instalador comprobará la disponibilidad de actualizaciones y enviará el dominio de esta instalación al servidor de actualizaciones.',
    'update_available_after_install' => 'Hay una versión más nueva %s disponible. Después de la instalación, abra Actualizaciones y actualice el sistema.',
    'confirm_install' => '¿Iniciar la instalación de CRM? Asegúrese de que todos los ajustes sean correctos.',
    'network_error' => 'Error de red',
    'install_failed' => 'La instalación falló. Consulte los registros del servidor.',
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'Configuración de API escribible',
    'preflight_storage_writable' => 'Almacenamiento escribible',
    'preflight_session' => 'Sesión',
    'preflight_file_info' => 'Fileinfo (finfo)',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS (dns_get_record)',
];

// Portuguese (Brazil)
$L['pt'] = [
    'title' => 'Instalação do CRM',
    'already_installed' => 'Sistema já instalado',
    'already_installed_desc' => 'O sistema já está instalado. Para reinstalar, remova o arquivo de configuração e o arquivo de bloqueio do instalador do diretório api/.',
    'go_to_dashboard' => 'Ir para o Painel de Controle',
    'step' => 'Etapa',
    'step_db' => 'Banco de Dados',
    'step_site' => 'Configurações do Site',
    'step_keys' => 'Chaves de Segurança',
    'step_install' => 'Instalar',
    'driver' => 'Driver',
    'mysql_only_note' => 'O instalador usa MySQL — o modo principal e suportado pelo CRM.',
    'requirements' => 'Verificação do ambiente',
    'requirement_ok' => 'Pronto',
    'requirement_fail' => 'Requer atenção',
    'host' => 'Host',
    'port' => 'Porta',
    'database' => 'Banco de dados',
    'username' => 'Usuário',
    'password' => 'Senha',
    'sqlite_path' => 'Caminho do arquivo SQLite',
    'test_connection' => 'Testar conexão',
    'testing' => 'Testando...',
    'connection_ok' => 'Conexão bem-sucedida',
    'connection_fail' => 'Falha na conexão',
    'next' => 'Próximo',
    'back' => 'Voltar',
    'site_url' => 'URL do site',
    'timezone' => 'Fuso horário',
    'admin_login' => 'Usuário',
    'admin_email' => 'Email do administrador',
    'admin_password' => 'Senha do administrador',
    'admin_password_confirm' => 'Confirmar senha',
    'admin_name' => 'Nome do administrador',
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => 'Regenerar tudo',
    'summary' => 'Resumo das configurações',
    'summary_driver' => 'Driver do BD',
    'summary_db_name' => 'Nome do BD',
    'summary_site_url' => 'URL do site',
    'summary_timezone' => 'Fuso horário',
    'summary_admin' => 'Administrador',
    'install_now' => 'Instalar',
    'installing' => 'Instalando...',
    'step_write_env' => 'Gravando arquivo .env',
    'step_create_tables' => 'Criando tabelas do banco de dados',
    'step_seed_data' => 'Preenchendo dados de referência',
    'step_create_admin' => 'Criando usuário administrador',
    'step_demo_data' => 'Criando dados de demonstração',
    'step_finalize' => 'Finalizando',
    'install_success' => 'Instalação concluída!',
    'install_success_desc' => 'O sistema foi instalado com sucesso. Use as credenciais abaixo para fazer login.',
    'recovery_key_title' => 'Chave de recuperação',
    'recovery_key_desc' => 'Salve-a agora: ela é necessária para o acesso de emergência a /updater/rescue.php se uma atualização deixar o CRM em modo de manutenção. A chave é exibida apenas uma vez.',
    'recovery_key_value' => 'Chave',
    'login_credentials' => 'Credenciais de acesso',
    'url' => 'URL',
    'login_label' => 'Usuário',
    'required_field' => 'Campo obrigatório',
    'passwords_mismatch' => 'As senhas não coincidem',
    'password_min_length' => 'Mínimo de 12 caracteres (maiúsculas, minúsculas, números, especiais)',
    'invalid_email' => 'Email inválido',
    'invalid_url' => 'URL inválida',
    'show' => 'Mostrar',
    'hide' => 'Ocultar',
    'error_occurred' => 'Ocorreu um erro',
    'db_connect_error' => 'Não foi possível conectar ao banco de dados',
    'env_write_error' => 'Erro ao gravar o arquivo .env',
    'table_create_error' => 'Erro ao criar as tabelas',
    'lock_file_error' => 'Sistema já instalado. Para reinstalar, exclua api/.env, api/.install.lock e storage_api/install.lock.',
    'install' => 'Instalação',
    'optional' => 'opcional',
    'update_check_notice' => 'O instalador verificará a disponibilidade de atualizações e enviará o domínio desta instalação ao servidor de atualizações.',
    'update_available_after_install' => 'Uma versão mais recente %s está disponível. Após a instalação, abra Atualizações e atualize o sistema.',
    'confirm_install' => 'Iniciar a instalação do CRM? Verifique se todas as configurações estão corretas.',
    'network_error' => 'Erro de rede',
    'install_failed' => 'A instalação falhou. Verifique os logs do servidor.',
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'Configuração da API gravável',
    'preflight_storage_writable' => 'Armazenamento gravável',
    'preflight_session' => 'Sessão',
    'preflight_file_info' => 'Fileinfo (finfo)',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS (dns_get_record)',
];

// German
$L['de'] = [
    'title' => 'CRM-Installation',
    'already_installed' => 'System bereits installiert',
    'already_installed_desc' => 'System ist bereits installiert. Zum Neuinstallieren entfernen Sie die Konfigurationsdatei und die Installer-Sperrdatei aus dem api/-Verzeichnis.',
    'go_to_dashboard' => 'Zum Dashboard',
    'step' => 'Schritt',
    'step_db' => 'Datenbank',
    'step_site' => 'Website-Einstellungen',
    'step_keys' => 'Sicherheitsschlüssel',
    'step_install' => 'Installieren',
    'driver' => 'Treiber',
    'mysql_only_note' => 'Der Installer verwendet MySQL — der unterstützte CRM-Modus.',
    'requirements' => 'Umgebungsprüfung',
    'requirement_ok' => 'Bereit',
    'requirement_fail' => 'Erfordert Aufmerksamkeit',
    'host' => 'Host',
    'port' => 'Port',
    'database' => 'Datenbank',
    'username' => 'Benutzername',
    'password' => 'Passwort',
    'sqlite_path' => 'SQLite-Dateipfad',
    'test_connection' => 'Verbindung testen',
    'testing' => 'Teste...',
    'connection_ok' => 'Verbindung erfolgreich',
    'connection_fail' => 'Verbindung fehlgeschlagen',
    'next' => 'Weiter',
    'back' => 'Zurück',
    'site_url' => 'Website-URL',
    'timezone' => 'Zeitzone',
    'admin_login' => 'Benutzername',
    'admin_email' => 'Admin-E-Mail',
    'admin_password' => 'Admin-Passwort',
    'admin_password_confirm' => 'Passwort bestätigen',
    'admin_name' => 'Admin-Name',
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => 'Alle neu generieren',
    'summary' => 'Einstellungsübersicht',
    'summary_driver' => 'DB-Treiber',
    'summary_db_name' => 'DB-Name',
    'summary_site_url' => 'Website-URL',
    'summary_timezone' => 'Zeitzone',
    'summary_admin' => 'Administrator',
    'install_now' => 'Installieren',
    'installing' => 'Installation läuft...',
    'step_write_env' => 'Schreibe .env-Datei',
    'step_create_tables' => 'Erstelle Datenbanktabellen',
    'step_seed_data' => 'Fülle Referenzdaten',
    'step_create_admin' => 'Erstelle Admin-Benutzer',
    'step_demo_data' => 'Erstelle Demo-Daten',
    'step_finalize' => 'Abschluss',
    'install_success' => 'Installation abgeschlossen!',
    'install_success_desc' => 'Das System wurde erfolgreich installiert. Verwenden Sie die Anmeldedaten unten zum Einloggen.',
    'recovery_key_title' => 'Wiederherstellungsschlüssel',
    'recovery_key_desc' => 'Speichern Sie ihn jetzt - er wird für den Notfallzugang zu /updater/rescue.php benötigt, falls ein Update die CRM im Wartungsmodus lässt. Der Schlüssel wird nur einmal angezeigt.',
    'recovery_key_value' => 'Schlüssel',
    'login_credentials' => 'Anmeldedaten',
    'url' => 'URL',
    'login_label' => 'Benutzername',
    'required_field' => 'Pflichtfeld',
    'passwords_mismatch' => 'Passwörter stimmen nicht überein',
    'password_min_length' => 'Mindestens 12 Zeichen (Groß-/Kleinbuchstaben, Zahlen, Sonderzeichen)',
    'invalid_email' => 'Ungültige E-Mail',
    'invalid_url' => 'Ungültige URL',
    'show' => 'Anzeigen',
    'hide' => 'Ausblenden',
    'error_occurred' => 'Ein Fehler ist aufgetreten',
    'db_connect_error' => 'Verbindung zur Datenbank fehlgeschlagen',
    'env_write_error' => 'Fehler beim Schreiben der .env-Datei',
    'table_create_error' => 'Fehler beim Erstellen der Tabellen',
    'lock_file_error' => 'System bereits installiert. Zum Neuinstallieren löschen Sie api/.env, api/.install.lock und storage_api/install.lock.',
    'install' => 'Installation',
    'optional' => 'optional',
    'update_check_notice' => 'Der Installer prüft die Verfügbarkeit von Updates und übermittelt die Domain dieser Installation an den Update-Server.',
    'update_available_after_install' => 'Eine neuere Version %s ist verfügbar. Öffnen Sie nach der Installation den Bereich Updates und aktualisieren Sie das System.',
    'confirm_install' => 'CRM-Installation starten? Stellen Sie sicher, dass alle Einstellungen korrekt sind.',
    'network_error' => 'Netzwerkfehler',
    'install_failed' => 'Installation fehlgeschlagen. Prüfen Sie die Serverprotokolle.',
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'API-Konfiguration beschreibbar',
    'preflight_storage_writable' => 'Speicher beschreibbar',
    'preflight_session' => 'Sitzung',
    'preflight_file_info' => 'Fileinfo (finfo)',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS (dns_get_record)',
];

// French
$L['fr'] = [
    'title' => 'Installation de CRM',
    'already_installed' => 'Système déjà installé',
    'already_installed_desc' => 'Le système est déjà installé. Pour réinstaller, supprimez le fichier de configuration et le fichier de verrouillage d\'installation du répertoire api/.',
    'go_to_dashboard' => 'Aller au tableau de bord',
    'step' => 'Étape',
    'step_db' => 'Base de données',
    'step_site' => 'Paramètres du site',
    'step_keys' => 'Clés de sécurité',
    'step_install' => 'Installer',
    'driver' => 'Pilote',
    'mysql_only_note' => "L'installeur utilise MySQL — le mode principal et compatible avec CRM.",
    'requirements' => "Vérification de l'environnement",
    'requirement_ok' => 'Prêt',
    'requirement_fail' => 'Nécessite une attention',
    'host' => 'Hôte',
    'port' => 'Port',
    'database' => 'Base de données',
    'username' => "Nom d'utilisateur",
    'password' => 'Mot de passe',
    'sqlite_path' => 'Chemin du fichier SQLite',
    'test_connection' => 'Tester la connexion',
    'testing' => 'Test en cours...',
    'connection_ok' => 'Connexion réussie',
    'connection_fail' => 'Échec de la connexion',
    'next' => 'Suivant',
    'back' => 'Retour',
    'site_url' => 'URL du site',
    'timezone' => 'Fuseau horaire',
    'admin_login' => "Nom d'utilisateur",
    'admin_email' => "Email de l'administrateur",
    'admin_password' => "Mot de passe de l'administrateur",
    'admin_password_confirm' => 'Confirmer le mot de passe',
    'admin_name' => "Nom de l'administrateur",
    'app_key' => 'APP_KEY',
    'csrf_key' => 'CSRF_SECRET_KEY',
    'webhook_key' => 'WEBHOOK_SECRET_KEY',
    'ai_key' => 'AI_ENCRYPTION_KEY',
    'regenerate_all' => 'Tout régénérer',
    'summary' => 'Résumé des paramètres',
    'summary_driver' => 'Pilote BD',
    'summary_db_name' => 'Nom BD',
    'summary_site_url' => 'URL du site',
    'summary_timezone' => 'Fuseau horaire',
    'summary_admin' => 'Administrateur',
    'install_now' => 'Installer',
    'installing' => 'Installation en cours...',
    'step_write_env' => "Écriture du fichier .env",
    'step_create_tables' => 'Création des tables de la base de données',
    'step_seed_data' => 'Remplissage des données de référence',
    'step_create_admin' => "Création de l'utilisateur administrateur",
    'step_demo_data' => 'Création des données de démonstration',
    'step_finalize' => 'Finalisation',
    'install_success' => 'Installation terminée !',
    'install_success_desc' => "Le système a été installé avec succès. Utilisez les identifiants ci-dessous pour vous connecter.",
    'recovery_key_title' => 'Clé de récupération',
    'recovery_key_desc' => "Enregistrez-la maintenant : elle est nécessaire pour l'accès d'urgence à /updater/rescue.php si une mise à jour laisse le CRM en mode maintenance. La clé n'est affichée qu'une seule fois.",
    'recovery_key_value' => 'Clé',
    'login_credentials' => 'Identifiants de connexion',
    'url' => 'URL',
    'login_label' => "Nom d'utilisateur",
    'required_field' => 'Champ obligatoire',
    'passwords_mismatch' => 'Les mots de passe ne correspondent pas',
    'password_min_length' => 'Minimum 12 caractères (majuscules, minuscules, chiffres, spéciaux)',
    'invalid_email' => 'Email invalide',
    'invalid_url' => 'URL invalide',
    'show' => 'Afficher',
    'hide' => 'Masquer',
    'error_occurred' => "Une erreur s'est produite",
    'db_connect_error' => 'Impossible de se connecter à la base de données',
    'env_write_error' => "Erreur lors de l'écriture du fichier .env",
    'table_create_error' => 'Erreur lors de la création des tables',
    'lock_file_error' => "Système déjà installé. Pour réinstaller, supprimez api/.env, api/.install.lock et storage_api/install.lock.",
    'install' => 'Installation',
    'optional' => 'optionnel',
    'update_check_notice' => "L'installateur vérifiera la disponibilité des mises à jour et enverra le domaine de cette installation au serveur de mises à jour.",
    'update_available_after_install' => "Une version plus récente %s est disponible. Après l'installation, ouvrez Mises à jour et mettez à jour le système.",
    'confirm_install' => "Démarrer l'installation de CRM ? Vérifiez que tous les paramètres sont corrects.",
    'network_error' => 'Erreur réseau',
    'install_failed' => "Échec de l'installation. Consultez les journaux du serveur.",
    'preflight_php' => 'PHP 8.1+',
    'preflight_pdo_mysql' => 'PDO MySQL',
    'preflight_api_writable' => 'Configuration API accessible en écriture',
    'preflight_storage_writable' => 'Stockage accessible en écriture',
    'preflight_session' => 'Session',
    'preflight_file_info' => 'Fileinfo (finfo)',
    'preflight_curl' => 'cURL',
    'preflight_openssl' => 'OpenSSL',
    'preflight_dns' => 'DNS (dns_get_record)',
];

// SEC-001: Block access after installation — return 410 Gone immediately.
// This check intentionally runs after language/session initialization so the
// response can use the selected locale without duplicating the language map.
$lockFiles = [
    __DIR__ . '/../api/.install.lock',
    __DIR__ . '/../storage_api/install.lock',
];
foreach ($lockFiles as $lockFile) {
    $resolved = realpath($lockFile);
    if ($resolved !== false && is_file($resolved)) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo t('already_installed');
        exit;
    }
}

// ============================================================================
// Part 4: Helper Functions
// ============================================================================

function t(string $key): string
{
    global $L, $lang;
    return $L[$lang][$key] ?? $L['en'][$key] ?? $key;
}

// SEC-011: Guard e() with function_exists so it coexists with the global
// web/helpers.php version (which is loaded only outside the installer flow,
// but a defensive guard is cheap and prevents a future refactor from
// introducing a "Cannot redeclare e()" fatal).
if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e($_SESSION['csrf_token']) . '">';
}

function csrfCheck(): bool
{
    $token = trim((string)($_POST['_csrf'] ?? ''));
    if ($token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    // Regenerate only for non-AJAX form submits (AJAX install runs multiple substeps)
    if (!isset($_POST['_ajax'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return true;
}

function generateRandomHex(int $bytes = 32): string
{
    try {
        return bin2hex(random_bytes($bytes));
    } catch (\Throwable $e) {
        error_log('[Install::generateRandomHex] random_bytes failed: ' . $e->getMessage());
        // Fallback: use openssl if random_bytes fails
        try {
            $data = '';
            if (function_exists('openssl_random_pseudo_bytes')) {
                $data = openssl_random_pseudo_bytes($bytes);
            }
            if ($data !== '' && $data !== false) {
                return bin2hex($data);
            }
        } catch (\Throwable $e) {
            error_log('[Install::generateRandomHex] ' . $e->getMessage());
            // Give up
        }
        // Last resort: deterministic fallback (not cryptographically secure, but prevents crash)
        return hash('sha256', microtime(true) . random_int(0, PHP_INT_MAX));
    }
}

function generateVapidKeys(): array
{
    try {
        if (!function_exists('openssl_pkey_new')) {
            return ['public_key' => '', 'private_key' => ''];
        }

        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key === false) {
            return ['public_key' => '', 'private_key' => ''];
        }

        $details = openssl_pkey_get_details($key);
        if (!isset($details['ec'])) {
            return ['public_key' => '', 'private_key' => ''];
        }

        $ec = $details['ec'];
        $publicKeyRaw = "\x04" . $ec['x'] . $ec['y'];
        $publicKey = rtrim(strtr(base64_encode($publicKeyRaw), '+/', '-_'), '=');
        $privateKey = rtrim(strtr(base64_encode($ec['d']), '+/', '-_'), '=');

        return ['public_key' => $publicKey, 'private_key' => $privateKey];
    } catch (\Throwable $e) {
        error_log('[Install::generateVapidKeys] ' . $e->getMessage());
        return ['public_key' => '', 'private_key' => ''];
    }
}

function sanitizeInput(string $value): string
{
    return trim(strip_tags($value));
}

function redirectToDashboard(): void
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = rtrim(str_replace('install.php', '', $scriptName), '/');
    $url = ($base ?: '') . '/index.php?route=dashboard';
    header('Location: ' . $url, true, 302);
    exit;
}

function autoDetectSiteUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/install.php';
    $base = rtrim(str_replace('/install.php', '', $script), '/');
    $port = $_SERVER['SERVER_PORT'] ?? '';
    if (($scheme === 'http' && $port === '80') || ($scheme === 'https' && $port === '443')) {
        $port = '';
    }
    if ($port !== '' && !str_contains($host, ':')) {
        $host .= ':' . $port;
    }
    return rtrim($scheme . '://' . $host . $base, '/');
}

function normalizedInstallDomain(?string $url = null): string
{
    $host = '';
    if ($url !== null && trim($url) !== '') {
        $host = (string)(parse_url(trim($url), PHP_URL_HOST) ?: '');
        if ($host === '' && !str_contains($url, '://')) {
            $host = (string)(parse_url('https://' . trim($url), PHP_URL_HOST) ?: '');
        }
    }
    if ($host === '') {
        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    }
    $host = strtolower(trim($host));
    if (str_starts_with($host, '[')) {
        $end = strpos($host, ']');
        return $end === false ? '' : substr($host, 1, $end - 1);
    }
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }
    return preg_match('/^[a-z0-9.-]+$/', $host) === 1 ? trim($host, '.') : '';
}

function installingCoreVersion(): string
{
    $versionFile = dirname(__DIR__) . '/VERSION';
    if (is_file($versionFile)) {
        $version = trim((string)file_get_contents($versionFile));
        if ($version !== '') {
            return $version;
        }
    }
    return INSTALL_VERSION;
}

function checkLatestCoreVersion(array $installData): ?array
{
    $domain = normalizedInstallDomain((string)($installData['site_url'] ?? autoDetectSiteUrl()));
    $query = http_build_query(array_filter([
        'installation_domain' => $domain,
        'context' => 'install',
        'installed_version' => installingCoreVersion(),
    ], static fn($value): bool => $value !== null && $value !== ''));
    // A fresh install has no known build yet, so ask the update center for the
    // plan as if starting from build 0 (exactly what the CRM's own planner does
    // for an unknown local core). This compares BUILD numbers, so the notice
    // actually fires when a newer build was published - a plain semver compare
    // could never fire because every build ships the same VERSION (1.0.0).
    $planUrl = rtrim(UPDATE_CENTER_URL, '/') . '/api/v1/products/' . rawurlencode(UPDATE_PRODUCT)
        . '/update-plan?current_build=0&channel=' . rawurlencode(UPDATE_CHANNEL);
    $response = @file_get_contents($planUrl, false, stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]));
    if ($response !== false) {
        $plan = json_decode($response, true);
        if (is_array($plan)) {
            $targetBuild = trim((string)($plan['target_build'] ?? ''));
            if ($targetBuild !== '') {
                return [
                    'latest_version' => $targetBuild,
                    'latest_build' => $targetBuild,
                    'current_version' => installingCoreVersion(),
                    'update_available' => ($plan['update_available'] ?? false) === true,
                ];
            }
        }
    }

    // Fallback: channel metadata (best effort - the update center cannot infer
    // a fresh install's own build from its semver, so any published build is
    // reported as an update).
    $query = http_build_query(array_filter([
        'installation_domain' => $domain,
        'context' => 'install',
        'installed_version' => installingCoreVersion(),
    ], static fn($value): bool => $value !== null && $value !== ''));
    $url = rtrim(UPDATE_CENTER_URL, '/') . '/api/v1/products/' . rawurlencode(UPDATE_PRODUCT) . '/channels/' . rawurlencode(UPDATE_CHANNEL) . ($query !== '' ? '?' . $query : '');
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]));
    if ($response === false) {
        return null;
    }
    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return null;
    }
    $latestBuild = trim((string)($payload['latest_build'] ?? ''));
    if ($latestBuild === '') {
        return null;
    }
    return [
        'latest_version' => $latestBuild,
        'latest_build' => $latestBuild,
        'current_version' => installingCoreVersion(),
        'update_available' => true,
    ];
}

function installUpdateNotice(array $installData): ?string
{
    try {
        $latest = checkLatestCoreVersion($installData);
    } catch (\Throwable $e) {
        error_log('[Install::installUpdateNotice] ' . $e->getMessage());
        return null;
    }
    if (!is_array($latest) || ($latest['update_available'] ?? false) !== true) {
        return null;
    }
    return sprintf(t('update_available_after_install'), (string)$latest['latest_version']);
}

function sqliteDatabasePath(): string
{
    $storageBase = rtrim(STORAGE_BASE_DEFAULT, '/\\');
    $tempDir = $storageBase . '/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    return $tempDir . '/crm.sqlite';
}

function installerStorageBase(): string
{
    return rtrim(STORAGE_BASE_DEFAULT, '/\\');
}

function getPreflightChecks(): array
{
    $apiDir = dirname(ENV_FILE_PATH);
    $storageBase = installerStorageBase();
    $storageParent = dirname($storageBase);
    $pdoDrivers = PDO::getAvailableDrivers();

    return [
        [
            'label' => t('preflight_php'),
            'ok' => PHP_VERSION_ID >= 80100,
            'detail' => PHP_VERSION,
        ],
        [
            'label' => t('preflight_pdo_mysql'),
            'ok' => extension_loaded('pdo_mysql') && in_array('mysql', $pdoDrivers, true),
            'detail' => extension_loaded('pdo_mysql') ? 'pdo_mysql' : 'missing',
        ],
        [
            'label' => t('preflight_api_writable'),
            'ok' => is_dir($apiDir) ? is_writable($apiDir) : is_writable(dirname($apiDir)),
            'detail' => $apiDir,
        ],
        [
            'label' => t('preflight_storage_writable'),
            'ok' => is_dir($storageBase) ? is_writable($storageBase) : is_writable($storageParent),
            'detail' => $storageBase,
        ],
        [
            'label' => t('preflight_session'),
            'ok' => session_status() === PHP_SESSION_ACTIVE,
            'detail' => session_name(),
        ],
        [
            'label' => t('preflight_file_info'),
            'ok' => function_exists('finfo_open') || function_exists('mime_content_type'),
            'detail' => function_exists('finfo_open') ? 'finfo' : (function_exists('mime_content_type') ? 'mime_content_type' : 'missing'),
        ],
        [
            'label' => t('preflight_curl'),
            'ok' => function_exists('curl_init'),
            'detail' => function_exists('curl_init') ? 'curl' : 'missing',
        ],
        [
            'label' => t('preflight_openssl'),
            'ok' => extension_loaded('openssl'),
            'detail' => extension_loaded('openssl') ? 'openssl' : 'missing',
        ],
        [
            'label' => t('preflight_dns'),
            'ok' => function_exists('dns_get_record'),
            'detail' => function_exists('dns_get_record') ? 'dns_get_record' : 'missing (webhook security reduced)',
        ],
    ];
}

function hasBlockingPreflightFailure(): bool
{
    foreach (getPreflightChecks() as $check) {
        if (empty($check['ok'])) {
            return true;
        }
    }
    return false;
}

function getTimezones(): array
{
    return [
        'Europe/Moscow' => 'Europe/Moscow (UTC+3)',
        'Europe/London' => 'Europe/London (UTC+0/+1)',
        'Europe/Berlin' => 'Europe/Berlin (UTC+1/+2)',
        'Europe/Paris' => 'Europe/Paris (UTC+1/+2)',
        'Europe/Istanbul' => 'Europe/Istanbul (UTC+3)',
        'Europe/Kaliningrad' => 'Europe/Kaliningrad (UTC+2)',
        'Europe/Samara' => 'Europe/Samara (UTC+4)',
        'Europe/Yekaterinburg' => 'Europe/Yekaterinburg (UTC+5)',
        'Asia/Omsk' => 'Asia/Omsk (UTC+6)',
        'Asia/Krasnoyarsk' => 'Asia/Krasnoyarsk (UTC+7)',
        'Asia/Irkutsk' => 'Asia/Irkutsk (UTC+8)',
        'Asia/Yakutsk' => 'Asia/Yakutsk (UTC+9)',
        'Asia/Vladivostok' => 'Asia/Vladivostok (UTC+10)',
        'Asia/Magadan' => 'Asia/Magadan (UTC+11)',
        'Asia/Kamchatka' => 'Asia/Kamchatka (UTC+12)',
        'America/New_York' => 'America/New_York (UTC-5/-4)',
        'America/Chicago' => 'America/Chicago (UTC-6/-5)',
        'America/Denver' => 'America/Denver (UTC-7/-6)',
        'America/Los_Angeles' => 'America/Los_Angeles (UTC-8/-7)',
        'America/Sao_Paulo' => 'America/Sao_Paulo (UTC-3)',
        'Asia/Shanghai' => 'Asia/Shanghai (UTC+8)',
        'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)',
        'Asia/Seoul' => 'Asia/Seoul (UTC+9)',
        'Asia/Singapore' => 'Asia/Singapore (UTC+8)',
        'Asia/Kolkata' => 'Asia/Kolkata (UTC+5:30)',
        'Asia/Dubai' => 'Asia/Dubai (UTC+4)',
        'Asia/Almaty' => 'Asia/Almaty (UTC+5)',
        'Asia/Bishkek' => 'Asia/Bishkek (UTC+6)',
        'Asia/Tashkent' => 'Asia/Tashkent (UTC+5)',
        'Asia/Baku' => 'Asia/Baku (UTC+4)',
        'Asia/Yerevan' => 'Asia/Yerevan (UTC+4)',
        'Asia/Tbilisi' => 'Asia/Tbilisi (UTC+4)',
        'Europe/Minsk' => 'Europe/Minsk (UTC+3)',
        'Europe/Kiev' => 'Europe/Kiev (UTC+2/+3)',
        'Australia/Sydney' => 'Australia/Sydney (UTC+10/+11)',
        'Pacific/Auckland' => 'Pacific/Auckland (UTC+12/+13)',
        'UTC' => 'UTC',
    ];
}

// Validate/normalize a MySQL host so it can never break out of the DSN's
// host= segment (reject ";", whitespace, quotes). Allows hostnames, IPv4,
// bracketed IPv6, and UNIX socket paths (which contain "/").
function normalizeDbHost(string $host): string
{
    $host = trim($host);
    if ($host === '') {
        throw new RuntimeException('DB host is required.');
    }
    if (preg_match('/^[a-zA-Z0-9._\-:\/\[\]]+$/', $host) !== 1) {
        throw new RuntimeException('DB host contains unsupported characters.');
    }
    return $host;
}

function normalizeDbPort(int $port): int
{
    return max(1, min(65535, $port));
}

function testDatabaseConnection(string $driver, string $host, int $port, string $database, string $username, string $password): array
{
    try {
        if ($driver !== SUPPORTED_DB_DRIVER) {
            throw new RuntimeException('Only MySQL is supported by this installer.');
        }

        $host = normalizeDbHost($host);
        $port = normalizeDbPort($port);

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);

        $pdo->query('SELECT 1');
        return ['success' => true, 'message' => t('connection_ok')];
    } catch (Throwable $e) {
        // SEC-001: Log full error details server-side, never expose to client
        error_log('[Installer] DB connection failed: ' . $e->getMessage());
        $errMsg = $e->getMessage();
        $hint = '';
        if (str_contains($errMsg, 'could not find driver')) {
            $hint = ' (' . (t('db_connect_error')) . ': pdo_mysql extension required)';
        } elseif (str_contains($errMsg, 'Access denied')) {
            $hint = ': ' . (t('db_connect_error'));
        } elseif (str_contains($errMsg, 'Unknown database')) {
            $hint = ': ' . (t('db_connect_error'));
        } elseif (str_contains($errMsg, 'Connection refused')) {
            $hint = ' (' . t('host') . '/' . t('port') . ')';
        }
        return ['success' => false, 'message' => t('connection_fail') . $hint];
    }
}

function isAlreadyInstalled(): bool
{
    if (!hasEnvConfig()) {
        return false;
    }

    // Check both lock files (api/.install.lock and storage_api/install.lock)
    $lockFiles = [
        LOCK_FILE_PATH,
        dirname(LOCK_FILE_PATH) . '/../../storage_api/install.lock',
    ];
    foreach ($lockFiles as $lockFile) {
        if (is_file($lockFile)) {
            return true;
        }
    }

    return canConnectFromEnv() && hasInstallStateFromEnv();
}

function parseEnvFile(string $path): array
{
    $env = [];
    if (!is_file($path)) {
        return $env;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $env;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        } elseif (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
    return $env;
}

function canConnectFromEnv(): bool
{
    $env = parseEnvFile(getEnvConfigPath());
    $driver = $env['DB_CONNECTION'] ?? '';
    if ($driver === '') {
        return false;
    }

    try {
        if ($driver === 'mysql') {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $env['DB_HOST'] ?? '127.0.0.1',
                    (int)($env['DB_PORT'] ?? 3306),
                    $env['DB_DATABASE'] ?? '',
                    $env['DB_CHARSET'] ?? 'utf8mb4'
                ),
                $env['DB_USERNAME'] ?? '',
                $env['DB_PASSWORD'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
            );
        } else {
            return false;
        }
        $pdo->query('SELECT 1');
        return true;
    } catch (\Throwable $e) {
        error_log('[Install::canConnectFromEnv] ' . $e->getMessage());
        return false;
    }
}

function pdoFromEnvFile(): ?PDO
{
    if (!hasEnvConfig()) {
        return null;
    }
    $env = parseEnvFile(getEnvConfigPath());
    if (($env['DB_CONNECTION'] ?? '') !== 'mysql') {
        return null;
    }
    try {
        return getPdoConnection([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $env['DB_HOST'] ?? '127.0.0.1',
            'DB_PORT' => $env['DB_PORT'] ?? '3306',
            'DB_DATABASE' => $env['DB_DATABASE'] ?? '',
            'DB_USERNAME' => $env['DB_USERNAME'] ?? '',
            'DB_PASSWORD' => $env['DB_PASSWORD'] ?? '',
            'DB_CHARSET' => $env['DB_CHARSET'] ?? 'utf8mb4',
        ]);
    } catch (\Throwable $e) {
        error_log('[Install::pdoFromEnvFile] ' . $e->getMessage());
        return null;
    }
}

function hasInstallStateFromEnv(): bool
{
    $pdo = pdoFromEnvFile();
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'install_state'");
        if (!$stmt || !$stmt->fetchColumn()) {
            return false;
        }
        return (int)$pdo->query('SELECT COUNT(*) FROM install_state')->fetchColumn() > 0;
    } catch (\Throwable $e) {
        error_log('[Install::hasInstallStateFromEnv] DB query failed: ' . $e->getMessage());
        return false;
    }
}

function getPdoConnection(array $env): PDO
{
    $driver = $env['DB_CONNECTION'] ?? 'mysql';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ];

    if ($driver === 'mysql') {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                normalizeDbHost((string)($env['DB_HOST'] ?? '127.0.0.1')),
                normalizeDbPort((int)($env['DB_PORT'] ?? 3306)),
                $env['DB_DATABASE'] ?? '',
                $env['DB_CHARSET'] ?? 'utf8mb4'
            ),
            $env['DB_USERNAME'] ?? '',
            $env['DB_PASSWORD'] ?? '',
            $options
        );
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }

    throw new RuntimeException('Unsupported driver: ' . $driver);
}

function sanitizeEnvValue(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

// Quote a value for api/.env. EnvLoader::parseValue() strips unquoted inline
// comments (\s+#.*$) and trims whitespace, which would corrupt DB passwords or
// site URLs containing "#", spaces, or leading/trailing whitespace. Double
// quotes with escaping round-trip cleanly through EnvLoader, KeyGuard, and the
// installer's own parseEnvFile().
function quoteEnvValue(string $value): string
{
    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], sanitizeEnvValue($value));
    return '"' . $escaped . '"';
}

function writeEnvFile(array $data): bool
{
    $envContent = "# TropaTT — Environment Configuration\n";
    $envContent .= "# Generated by installer v" . INSTALL_VERSION . " on " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

    $envContent .= "# Runtime mode\n";
    $envContent .= "APP_ENV=production\n";
    $envContent .= "APP_DEBUG=0\n";
    $envContent .= "APP_TIMEZONE=" . quoteEnvValue((string)($data['timezone'] ?? 'Europe/Moscow')) . "\n\n";

    $envContent .= "# Storage\n";
    $envContent .= "CRM_STORAGE_BASE=" . STORAGE_BASE_DEFAULT . "\n\n";

    $envContent .= "# Database\n";
    $envContent .= "DB_CONNECTION=mysql\n";
    $envContent .= "DB_HOST=" . quoteEnvValue((string)($data['db_host'] ?? '127.0.0.1')) . "\n";
    $envContent .= "DB_PORT=" . quoteEnvValue((string)($data['db_port'] ?? '3306')) . "\n";
    $envContent .= "DB_DATABASE=" . quoteEnvValue((string)($data['db_database'] ?? '')) . "\n";
    $envContent .= "DB_USERNAME=" . quoteEnvValue((string)($data['db_username'] ?? '')) . "\n";
    $envContent .= "DB_PASSWORD=" . quoteEnvValue((string)($data['db_password'] ?? '')) . "\n";
    $envContent .= "DB_CHARSET=utf8mb4\n";

    $envContent .= "\n# Security secrets\n";
    $envContent .= "APP_KEY=" . quoteEnvValue((string)($data['app_key'] ?? '')) . "\n";
    $envContent .= "CSRF_SECRET_KEY=" . quoteEnvValue((string)($data['csrf_key'] ?? '')) . "\n";
    $envContent .= "WEBHOOK_SECRET_KEY=" . quoteEnvValue((string)($data['webhook_key'] ?? '')) . "\n";
    $envContent .= "AI_ENCRYPTION_KEY=" . quoteEnvValue((string)($data['ai_key'] ?? '')) . "\n";
    $envContent .= "CRON_SECRET_KEY=" . quoteEnvValue((string)($data['cron_secret'] ?? '')) . "\n\n";

    $siteUrl = rtrim(sanitizeEnvValue((string)($data['site_url'] ?? 'http://localhost')), '/');
    $envContent .= "# CORS allowlist\n";
    $envContent .= "CORS_ALLOW_ORIGIN=" . quoteEnvValue($siteUrl) . "\n\n";

    $envContent .= "# Install bootstrap secret\n";
    $envContent .= "INSTALL_BOOTSTRAP_SECRET=" . quoteEnvValue(bin2hex(random_bytes(16))) . "\n\n";

    $envContent .= "# Optional push gateway\n";
    $envContent .= "NOTIFICATIONS_PUSH_GATEWAY_URL=\n";

    $vapidKeys = generateVapidKeys();
    $envContent .= "NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY=" . quoteEnvValue((string)$vapidKeys['public_key']) . "\n";
    $envContent .= "NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY=" . quoteEnvValue((string)$vapidKeys['private_key']) . "\n";
    $envContent .= "NOTIFICATIONS_PUSH_VAPID_SUBJECT=" . quoteEnvValue('mailto:' . (string)($data['admin_email'] ?? 'admin@example.com')) . "\n";

    $envContent .= "NOTIFICATIONS_PUSH_TIMEOUT_SEC=5\n";
    $envContent .= "NOTIFICATIONS_PUSH_MAX_SUBSCRIPTIONS_PER_DISPATCH=100\n";

    $dir = dirname(ENV_FILE_PATH);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return false;
    }

    if (file_put_contents(ENV_FILE_PATH, $envContent) === false) {
        return false;
    }
    // SEC: restrict .env to the owning user (secrets file)
    @chmod(ENV_FILE_PATH, 0600);
    return true;
}

function createDatabaseTables(PDO $pdo, string $driver): array
{
    if ($driver === 'mysql' && is_file(MYSQL_SCHEMA_SNAPSHOT_PATH)) {
        return importMysqlSchemaSnapshot($pdo);
    }

    $id = match ($driver) {
        'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'pgsql' => 'SERIAL PRIMARY KEY',
        default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
    };

    $bool = 'INTEGER';
    $text = 'TEXT';
    $dt = 'DATETIME';
    $bigint = 'BIGINT';

    $tables = [
        "CREATE TABLE IF NOT EXISTS install_state (
            id {$id},
            installed_at {$dt},
            version VARCHAR(20),
            payload {$text}
        )",

        "CREATE TABLE IF NOT EXISTS roles (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            code VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            is_system {$bool} DEFAULT 0,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS permissions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            code VARCHAR(128) UNIQUE,
            title VARCHAR(255),
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS role_permissions (
            id {$id},
            role_id INTEGER,
            permission_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS users (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            login VARCHAR(120) UNIQUE,
            email VARCHAR(190),
            password_hash VARCHAR(255),
            auth_token_hash VARCHAR(255),
            full_name VARCHAR(255),
            locale VARCHAR(16),
            is_active {$bool} DEFAULT 1,
            is_root {$bool} DEFAULT 0,
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt},
            deleted_at {$dt} NULL,
            cost_rate DECIMAL(12,2) DEFAULT NULL,
            bill_rate DECIMAL(12,2) DEFAULT NULL,
            payout_rate DECIMAL(12,2) DEFAULT NULL
        )",
 
        "CREATE TABLE IF NOT EXISTS user_roles (
            id {$id},
            user_id INTEGER,
            role_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS user_sessions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            token_hash VARCHAR(255),
            ip VARCHAR(128),
            user_agent {$text},
            device_fingerprint VARCHAR(64) NULL,
            device_name VARCHAR(190) NULL,
            expires_at {$dt},
            revoked_at {$dt} NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS api_clients (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            scopes {$text},
            is_active {$bool} DEFAULT 1,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS api_keys (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            client_id INTEGER,
            user_id INTEGER NULL,
            key_hash VARCHAR(255),
            scopes {$text},
            expires_at {$dt} NULL,
            revoked_at {$dt} NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS projects (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            description {$text},
            status_code VARCHAR(64),
            priority_code VARCHAR(64),
            client_public_id VARCHAR(64) NULL,
            manager_user_id INTEGER NULL,
            team_public_id VARCHAR(64) NULL,
            archived_at {$dt} NULL,
            created_by_user_id INTEGER,
            created_at {$dt},
            updated_at {$dt},
            row_version INTEGER DEFAULT 1
        )",

        "CREATE TABLE IF NOT EXISTS tasks (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            project_id INTEGER NULL,
            parent_task_id INTEGER NULL,
            title VARCHAR(255),
            description {$text},
            status_code VARCHAR(64),
            priority_code VARCHAR(64),
            sla_breached {$bool} DEFAULT 0,
            due_at {$dt} NULL,
            start_at {$dt} NULL,
            end_at {$dt} NULL,
            assignee_user_id INTEGER NULL,
            creator_user_id INTEGER,
            archived_at {$dt} NULL,
            deleted_at {$dt} NULL,
            created_at {$dt},
            updated_at {$dt},
            row_version INTEGER DEFAULT 1
        )",

        "CREATE TABLE IF NOT EXISTS task_relations (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            parent_task_id INTEGER NULL,
            child_task_id INTEGER NULL,
            relation_type VARCHAR(32) DEFAULT 'subtask',
            sort_order INTEGER DEFAULT 0,
            legacy_subtask_public_id VARCHAR(64) NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS task_assignees (
            id {$id},
            task_id INTEGER,
            user_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS task_watchers (
            id {$id},
            task_id INTEGER,
            user_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS task_status_history (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            task_id INTEGER,
            old_status VARCHAR(64),
            new_status VARCHAR(64),
            changed_by_user_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS comments (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            task_id INTEGER,
            project_id INTEGER NULL,
            author_user_id INTEGER,
            body {$text},
            visibility VARCHAR(32) DEFAULT 'internal',
            created_at {$dt},
            updated_at {$dt},
            deleted_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS comment_drafts (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            task_id INTEGER,
            body {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS files (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(32),
            entity_public_id VARCHAR(64),
            uploader_user_id INTEGER,
            original_name VARCHAR(255),
            storage_path {$text},
            mime_type VARCHAR(128),
            size_bytes {$bigint},
            is_deleted {$bool} DEFAULT 0,
            created_at {$dt},
            deleted_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS statuses (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            scope VARCHAR(64),
            code VARCHAR(64),
            title VARCHAR(255),
            color VARCHAR(32),
            sort_order INTEGER,
            is_active {$bool} DEFAULT 1,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS priorities (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            code VARCHAR(64),
            title VARCHAR(255),
            weight INTEGER,
            color VARCHAR(32),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS tags (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            code VARCHAR(64),
            title VARCHAR(255),
            color VARCHAR(32),
            description TEXT,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS entity_tags (
            id {$id},
            entity_type VARCHAR(32),
            entity_public_id VARCHAR(64),
            tag_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS notifications (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            category VARCHAR(64),
            title VARCHAR(255),
            body {$text},
            entity_type VARCHAR(64) NULL,
            entity_public_id VARCHAR(64) NULL,
            action_code VARCHAR(64) NULL,
            actor_user_id INTEGER NULL,
            actor_public_id VARCHAR(64) NULL,
            actor_name VARCHAR(255) NULL,
            link VARCHAR(1024) NULL,
            payload_json {$text} NULL,
            is_read {$bool} DEFAULT 0,
            created_at {$dt},
            read_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS chats (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255) NULL,
            type VARCHAR(32) DEFAULT 'direct',
            project_id INTEGER NULL,
            team_id INTEGER NULL,
            last_message_at {$dt} NULL,
            created_by_user_id INTEGER NULL,
            archived_at {$dt} NULL,
            archived_by_user_id INTEGER NULL,
            archived_participant_ids {$text} NULL,
            created_at {$dt},
            updated_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS chat_participants (
            id {$id},
            chat_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role VARCHAR(32) DEFAULT 'member',
            is_favorite {$bool} DEFAULT 0,
            muted_until {$dt} NULL,
            joined_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS chat_messages (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            chat_id INTEGER NOT NULL,
            sender_user_id INTEGER NOT NULL,
            reply_to_message_id INTEGER NULL,
            message_type VARCHAR(32) DEFAULT 'text',
            text {$text},
            created_at {$dt},
            edited_at {$dt} NULL,
            deleted_at {$dt} NULL,
            deleted_by_user_id INTEGER NULL
        )",

        "CREATE TABLE IF NOT EXISTS chat_message_audit_logs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            message_id INTEGER NOT NULL,
            chat_id INTEGER NOT NULL,
            actor_user_id INTEGER NOT NULL,
            action VARCHAR(32),
            before_text {$text} NULL,
            after_text {$text} NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS chat_read_markers (
            id {$id},
            chat_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            last_read_message_id INTEGER DEFAULT 0,
            updated_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS notification_push_subscriptions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            endpoint {$text},
            p256dh VARCHAR(1024),
            auth VARCHAR(1024),
            user_agent {$text} NULL,
            device_label VARCHAR(255) NULL,
            is_active {$bool} DEFAULT 1,
            last_error {$text} NULL,
            last_seen_at {$dt} NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS reminders (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            task_id INTEGER NULL,
            remind_at {$dt},
            status VARCHAR(32),
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS calendar_events (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            description {$text} NULL,
            starts_at {$dt},
            ends_at {$dt},
            owner_user_id INTEGER,
            project_id INTEGER NULL,
            task_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS work_logs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            task_id INTEGER,
            minutes_spent INTEGER,
            note {$text},
            logged_at {$dt},
            started_at {$dt} NULL,
            ended_at {$dt} NULL,
            activity_code VARCHAR(64) NULL,
            cost_rate_snapshot DECIMAL(12,2) NULL,
            bill_rate_snapshot DECIMAL(12,2) NULL,
            payout_rate_snapshot DECIMAL(12,2) NULL,
            currency_code VARCHAR(8) NULL,
            cost_source_type VARCHAR(32) NULL,
            cost_source_ref VARCHAR(64) NULL,
            bill_source_type VARCHAR(32) NULL,
            bill_source_ref VARCHAR(64) NULL,
            payout_source_type VARCHAR(32) NULL,
            payout_source_ref VARCHAR(64) NULL,
            rate_resolved_at {$dt} NULL,
            rate_ambiguous {$bool} DEFAULT 0,
            rate_locked_at {$dt} NULL,
            client_public_id VARCHAR(64) NULL,
            project_public_id VARCHAR(64) NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS settings (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            scope VARCHAR(64),
            name VARCHAR(190),
            value {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS request_logs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            request_id VARCHAR(64),
            correlation_id VARCHAR(64),
            user_public_id VARCHAR(64) NULL,
            route VARCHAR(255),
            method VARCHAR(16),
            status_code INTEGER,
            result_code VARCHAR(64),
            duration_ms INTEGER,
            payload {$text},
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS rate_limits (
            `key` VARCHAR(64) NOT NULL,
            attempts {$text} NOT NULL,
            blocked_until INTEGER NOT NULL DEFAULT 0,
            updated_at {$dt} NOT NULL,
            PRIMARY KEY (`key`)
        )",

        "CREATE TABLE IF NOT EXISTS audit_logs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            actor_public_id VARCHAR(64),
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            action VARCHAR(64),
            details {$text},
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS security_logs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            actor_public_id VARCHAR(64) NULL,
            event_type VARCHAR(64),
            ip VARCHAR(128),
            user_agent {$text},
            details {$text},
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS activity_feed (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            action VARCHAR(64),
            actor_public_id VARCHAR(64),
            payload {$text},
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS teams (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            manager_user_id INTEGER NULL,
            created_by_user_id INTEGER NULL,
            member_user_ids {$text} NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS departments (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            manager_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS companies (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS clients (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            company_id INTEGER NULL,
            title VARCHAR(255),
            client_type VARCHAR(32) NULL,
            legal_name VARCHAR(255) NULL,
            person_last_name VARCHAR(120) NULL,
            person_first_name VARCHAR(120) NULL,
            person_middle_name VARCHAR(120) NULL,
            person_birth_date DATE NULL,
            tax_inn VARCHAR(12) NULL,
            tax_kpp VARCHAR(9) NULL,
            tax_ogrn VARCHAR(13) NULL,
            tax_ogrnip VARCHAR(15) NULL,
            bank_account VARCHAR(34) NULL,
            bank_name VARCHAR(255) NULL,
            bank_bik VARCHAR(9) NULL,
            bank_corr_account VARCHAR(34) NULL,
            website VARCHAR(2048) NULL,
            messenger VARCHAR(190) NULL,
            address_legal {$text} NULL,
            address_postal {$text} NULL,
            notes {$text} NULL,
            extra_attributes {$text} NULL,
            email VARCHAR(190),
            phone VARCHAR(64),
            status VARCHAR(64),
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS contacts (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            company_id INTEGER NULL,
            client_id INTEGER NULL,
            full_name VARCHAR(255),
            email VARCHAR(190),
            phone VARCHAR(64),
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS subtasks (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            task_id INTEGER,
            title VARCHAR(255),
            status_code VARCHAR(64),
            assignee_user_id INTEGER NULL,
            sort_order INTEGER,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS checklists (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            task_id INTEGER,
            title VARCHAR(255),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS checklist_items (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            checklist_id INTEGER,
            title VARCHAR(255),
            is_done {$bool} DEFAULT 0,
            sort_order INTEGER,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS task_templates (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            payload {$text},
            is_active {$bool} DEFAULT 1,
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS project_templates (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            payload {$text},
            is_active {$bool} DEFAULT 1,
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS recurring_rules (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255) NULL,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            rrule {$text},
            is_active {$bool} DEFAULT 1,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS recurring_instances (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            rule_id INTEGER,
            entity_public_id VARCHAR(64),
            generated_at {$dt},
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS custom_fields (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            scope VARCHAR(64),
            code VARCHAR(64),
            title VARCHAR(255),
            type VARCHAR(64),
            options {$text},
            is_required {$bool} DEFAULT 0,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS custom_field_values (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            field_id INTEGER,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            value {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS automation_rules (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            trigger_code VARCHAR(64),
            action_code VARCHAR(64),
            payload {$text},
            is_enabled {$bool} DEFAULT 1,
            created_by_user_id INTEGER NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS automation_runs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            rule_id INTEGER,
            status VARCHAR(32),
            error {$text} NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS sla_policies (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            response_minutes INTEGER,
            resolve_minutes INTEGER,
            escalation_payload {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS approval_requests (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            requester_user_id INTEGER,
            status VARCHAR(32),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS approval_steps (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            request_id INTEGER,
            reviewer_user_id INTEGER,
            status VARCHAR(32),
            comment {$text} NULL,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS milestones (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            project_id INTEGER,
            title VARCHAR(255),
            due_at {$dt} NULL,
            status VARCHAR(32),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS task_dependencies (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            task_id INTEGER,
            depends_on_task_id INTEGER,
            dependency_type VARCHAR(32),
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS saved_views (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            entity_type VARCHAR(64),
            title VARCHAR(255),
            filters {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS favorites (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS mentions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            mentioned_user_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS reactions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            user_id INTEGER,
            reaction VARCHAR(32),
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS subscriptions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            user_id INTEGER,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS recycle_bin (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            entity_type VARCHAR(64),
            entity_public_id VARCHAR(64),
            payload {$text},
            deleted_by_user_id INTEGER,
            deleted_at {$dt},
            restored_at {$dt} NULL
        )",

        "CREATE TABLE IF NOT EXISTS import_jobs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            type VARCHAR(64),
            status VARCHAR(32),
            payload {$text},
            result {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS export_jobs (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            type VARCHAR(64),
            status VARCHAR(32),
            payload {$text},
            result {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS webhook_subscriptions (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            endpoint {$text},
            secret_hash VARCHAR(255),
            events {$text},
            is_active {$bool} DEFAULT 1,
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS webhook_deliveries (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            webhook_id INTEGER,
            event_code VARCHAR(64),
            status VARCHAR(32),
            response_code INTEGER NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS idempotency_keys (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            key_hash VARCHAR(255),
            route VARCHAR(255),
            response_payload {$text},
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS sync_state (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            scope VARCHAR(64),
            cursor_value VARCHAR(255),
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS business_calendars (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            timezone VARCHAR(64),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS holidays (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            calendar_id INTEGER,
            holiday_date DATE,
            title VARCHAR(255),
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS working_hours (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            calendar_id INTEGER,
            weekday INTEGER,
            start_time VARCHAR(8),
            end_time VARCHAR(8),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS feature_flags (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            code VARCHAR(128),
            is_enabled {$bool} DEFAULT 1,
            payload {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS organizations (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            title VARCHAR(255),
            slug VARCHAR(120),
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS invitations (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            email VARCHAR(190),
            invited_by_user_id INTEGER,
            token_hash VARCHAR(255),
            expires_at {$dt},
            accepted_at {$dt} NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            token_hash VARCHAR(255),
            expires_at {$dt},
            used_at {$dt} NULL,
            created_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS two_factor_secrets (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER,
            secret_hash VARCHAR(255),
            backup_codes {$text},
            created_at {$dt},
            updated_at {$dt}
        )",

        "CREATE TABLE IF NOT EXISTS impersonation_audit (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            admin_user_id INTEGER,
            target_user_id INTEGER,
            reason {$text},
            started_at {$dt},
            ended_at {$dt} NULL
        )",
    ];

    $errors = [];
    foreach ($tables as $index => $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('[Install::createDatabaseTables] Table #' . ($index + 1) . ': ' . $e->getMessage());
            $errors[] = 'Table creation failed. Check server logs for details.';
        }
    }

    if (empty($errors)) {
        createIndexes($pdo);
    }

    return $errors;
}

function runDatabaseMigrations(PDO $pdo, string $driver): array
{
    $autoloadErrors = registerApiAutoloader();
    if (!empty($autoloadErrors)) {
        return $autoloadErrors;
    }

    try {
        $schema = new Api\System\Library\Database\SchemaManager();
        $migrations = new Api\System\Library\Database\Migration\MigrationManager($schema);
        $migrations->migrateUp($pdo, $driver);
    } catch (Throwable $e) {
        error_log('[Install::runDatabaseMigrations] ' . $e->getMessage());
        return ['Database migration failed. Check server logs for details.'];
    }

    return [];
}

function importMysqlSchemaSnapshot(PDO $pdo): array
{
    $errors = executeSqlFile($pdo, MYSQL_SCHEMA_SNAPSHOT_PATH);
    if (!empty($errors)) {
        return $errors;
    }

    // IMPORTANT: do NOT mark migrations as applied here. The snapshot is a
    // historical schema dump; every migration added after it was generated
    // (new tables/columns such as counterparties.address_actual,
    // users.is_external, work_logs.start_at/end_at, core_update_*) is applied
    // by runDatabaseMigrations() right after this call. Marking everything
    // applied would leave fresh MySQL installs with an incomplete schema.
    return [];
}

function registerApiAutoloader(): array
{
    $apiBasePath = dirname(__DIR__) . '/api';
    $autoloaderPath = $apiBasePath . '/system/library/support/Autoloader.php';

    if (!is_file($autoloaderPath)) {
        return ['API autoloader not found: ' . $autoloaderPath];
    }

    try {
        require_once $autoloaderPath;

        if (class_exists(Api\System\Library\Support\Autoloader::class)) {
            (new Api\System\Library\Support\Autoloader($apiBasePath))->register();
        }
    } catch (Throwable $e) {
        error_log('[Install::registerApiAutoloader] ' . $e->getMessage());
        return ['API autoloader registration failed. Check server logs for details.'];
    }

    return [];
}

function executeSqlFile(PDO $pdo, string $path): array
{
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        return ['Schema snapshot is empty or unreadable: ' . $path];
    }

    $sql = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $sql);

    $errors = [];
    $statements = splitSqlStatements($sql);

    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            error_log('[Install::executeSqlFile] Statement #' . ($index + 1) . ': ' . $e->getMessage());
            $errors[] = 'Schema import failed. Check server logs for details.';
            if (count($errors) >= 5) {
                break;
            }
        }
    }

    return $errors;
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $escaped = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            $buffer = '';
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function createIndexes(PDO $pdo): void
{
    $indexes = [
        ['users', 'idx_users_login', ['login'], false],
        ['users', 'idx_users_created_by', ['created_by_user_id'], false],
        ['user_sessions', 'idx_sessions_token', ['token_hash'], false],
        ['tasks', 'idx_tasks_project', ['project_id'], false],
        ['tasks', 'idx_tasks_status', ['status_code'], false],
        ['tasks', 'idx_tasks_due', ['due_at'], false],
        ['comments', 'idx_comments_task', ['task_id'], false],
        ['companies', 'idx_companies_created_by', ['created_by_user_id'], false],
        ['teams', 'idx_teams_created_by', ['created_by_user_id'], false],
        ['clients', 'idx_clients_created_by', ['created_by_user_id'], false],
        ['clients', 'idx_clients_type', ['client_type'], false],
        ['contacts', 'idx_contacts_created_by', ['created_by_user_id'], false],
        ['files', 'idx_files_entity', ['entity_type', 'entity_public_id'], false],
        ['notifications', 'idx_notifications_user_created', ['user_id', 'created_at'], false],
        ['notifications', 'idx_notifications_user_unread_created', ['user_id', 'is_read', 'created_at'], false],
        ['notifications', 'idx_notifications_entity', ['entity_type', 'entity_public_id'], false],
        ['chats', 'idx_chats_last_message', ['last_message_at'], false],
        ['comment_drafts', 'uq_comment_drafts_user_task', ['user_id', 'task_id'], true],
        ['chat_participants', 'uq_chat_participant', ['chat_id', 'user_id'], true],
        ['chat_participants', 'idx_chat_participants_user', ['user_id', 'chat_id'], false],
        ['chat_messages', 'idx_chat_messages_chat_id', ['chat_id', 'id'], false],
        ['chat_read_markers', 'uq_chat_read', ['chat_id', 'user_id'], true],
        ['request_logs', 'idx_request_logs_request', ['request_id'], false],
        ['request_logs', 'idx_request_logs_created', ['created_at'], false],
        ['audit_logs', 'idx_audit_entity', ['entity_type', 'entity_public_id'], false],
        ['audit_logs', 'idx_audit_logs_created', ['created_at'], false],
        ['security_logs', 'idx_security_logs_created', ['created_at'], false],
    ];

    foreach ($indexes as [$table, $name, $columns, $unique]) {
        try {
            if (indexExists($pdo, $table, $name)) {
                continue;
            }
            $colStr = implode(', ', $columns);
            $type = $unique ? 'UNIQUE' : '';
            $pdo->exec("CREATE {$type} INDEX {$name} ON {$table}({$colStr})");
        } catch (\Throwable $e) {
            error_log('[Install::createIndexes] DB query failed: ' . $e->getMessage());
            // Index already exists or unsupported — safe to skip
        }
    }
}

function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    try {
        $stmt = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = :name");
        $stmt->execute(['name' => $indexName]);
        return (bool)$stmt->fetch();
    } catch (\Throwable $e) {
        error_log('[Install::indexExists] ' . $e->getMessage());
        return false;
    }
}

function seedDictionaries(PDO $pdo): void
{
    $now = gmdate('Y-m-d H:i:s');

    $statuses = [
        ['task', 'new', 'Новая', '#64748b', 10],
        ['task', 'in_progress', 'В работе', '#2563eb', 20],
        ['task', 'on_hold', 'На паузе', '#d97706', 30],
        ['task', 'blocked', 'Заблокирована', '#ef4444', 40],
        ['task', 'done', 'Завершена', '#16a34a', 50],
        ['project', 'active', 'Активный', '#2563eb', 10],
        ['project', 'on_hold', 'На паузе', '#d97706', 20],
        ['project', 'archived', 'В архиве', '#475569', 30],
        ['worklog_activity', 'dev', 'Разработка', '#2563eb', 10],
        ['worklog_activity', 'design', 'Дизайн', '#7c3aed', 20],
        ['worklog_activity', 'analysis', 'Аналитика', '#0891b2', 30],
        ['worklog_activity', 'consulting', 'Консультации', '#d97706', 40],
        ['worklog_activity', 'support', 'Поддержка', '#16a34a', 50],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO statuses (public_id, scope, code, title, color, sort_order, is_active, created_at, updated_at)
         VALUES (:public_id, :scope, :code, :title, :color, :sort_order, 1, :created_at, :updated_at)'
    );

    foreach ($statuses as $s) {
        $check = $pdo->prepare('SELECT id FROM statuses WHERE scope = :scope AND code = :code');
        $check->execute(['scope' => $s[0], 'code' => $s[1]]);
        if ($check->fetch()) {
            continue;
        }
        $insert->execute([
            'public_id' => 'sts_' . strtoupper(bin2hex(random_bytes(8))),
            'scope' => $s[0],
            'code' => $s[1],
            'title' => $s[2],
            'color' => $s[3],
            'sort_order' => $s[4],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $priorities = [
        ['low', 'Низкий', 10, '#16a34a'],
        ['normal', 'Средний', 20, '#2563eb'],
        ['high', 'Высокий', 30, '#f59e0b'],
        ['urgent', 'Срочно', 40, '#dc2626'],
    ];

    $pInsert = $pdo->prepare(
        'INSERT INTO priorities (public_id, code, title, weight, color, created_at, updated_at)
         VALUES (:public_id, :code, :title, :weight, :color, :created_at, :updated_at)'
    );

    foreach ($priorities as $p) {
        $check = $pdo->prepare('SELECT id FROM priorities WHERE code = :code');
        $check->execute(['code' => $p[0]]);
        if ($check->fetch()) {
            continue;
        }
        $pInsert->execute([
            'public_id' => 'pri_' . strtoupper(bin2hex(random_bytes(8))),
            'code' => $p[0],
            'title' => $p[1],
            'weight' => $p[2],
            'color' => $p[3],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function createAdminUser(PDO $pdo, array $data): array
{
    $now = gmdate('Y-m-d H:i:s');
    $email = $data['admin_email'] ?? 'admin@example.com';
    $login = $data['admin_login'] ?? $data['admin_email'] ?? 'admin';

    // Server-side password strength validation (L-1: minimum 12 chars + complexity)
    $password = (string)($data['admin_password'] ?? 'password');
    if (mb_strlen($password) < 12
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        throw new RuntimeException(t('password_min_length'));
    }

    $checkUser = $pdo->prepare('SELECT id, public_id FROM users WHERE email = :email OR login = :login');
    $checkUser->execute(['email' => $email, 'login' => $login]);
    $existing = $checkUser->fetch();

    if ($existing) {
        $userId = (int)$existing['id'];
        $publicId = $existing['public_id'];

        $pdo->prepare('UPDATE users SET password_hash = :hash, is_active = 1, is_root = 1, updated_at = :now WHERE id = :id')
            ->execute([
                'hash' => password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT),
                'now' => $now,
                'id' => $userId,
            ]);
    } else {
        $publicId = 'usr_' . strtoupper(bin2hex(random_bytes(8)));
        $pdo->prepare(
            'INSERT INTO users (public_id, login, email, password_hash, full_name, locale, is_active, is_root, created_at, updated_at)
             VALUES (:public_id, :login, :email, :password_hash, :full_name, :locale, 1, 1, :created_at, :updated_at)'
        )->execute([
            'public_id' => $publicId,
            'login' => $login,
            'email' => $email,
            'password_hash' => password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT),
            'full_name' => $data['admin_name'] ?? 'Administrator',
            'locale' => $data['lang'] ?? 'ru',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int)$pdo->lastInsertId();
    }

    $checkRole = $pdo->prepare('SELECT id FROM roles WHERE code = :code');
    $checkRole->execute(['code' => 'super_admin']);
    $roleExists = $checkRole->fetch();
    $roleId = $roleExists ? (int)$roleExists['id'] : 0;

    if (!$roleExists) {
        $rolePublicId = 'rol_' . strtoupper(bin2hex(random_bytes(8)));
        $pdo->prepare(
            'INSERT INTO roles (public_id, code, title, is_system, created_at, updated_at)
             VALUES (:public_id, :code, :title, 1, :created_at, :updated_at)'
        )->execute([
            'public_id' => $rolePublicId,
            'code' => 'super_admin',
            'title' => 'Супер-администратор',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $roleId = (int)$pdo->lastInsertId();
    }

    $checkUserRole = $pdo->prepare('SELECT id FROM user_roles WHERE user_id = :uid AND role_id = :rid');
    $checkUserRole->execute(['uid' => $userId, 'rid' => $roleId]);
    if (!$checkUserRole->fetch()) {
        $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, :created_at)'
        )->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => $now,
        ]);
    }

    return ['user_id' => $userId, 'public_id' => $publicId, 'role_id' => $roleId];
}

function createDemoData(PDO $pdo, array $adminUser): void
{
    $now = gmdate('Y-m-d H:i:s');
    $userId = $adminUser['user_id'];

    $existingDemo = $pdo->prepare('SELECT id FROM projects WHERE title = :title AND created_by_user_id = :uid LIMIT 1');
    $existingDemo->execute(['title' => 'Demo Project', 'uid' => $userId]);
    if ($existingDemo->fetch()) {
        return;
    }

    // Demo team
    $teamPublicId = 'tm_' . strtoupper(bin2hex(random_bytes(8)));
    $pdo->prepare(
        'INSERT INTO teams (public_id, title, manager_user_id, created_by_user_id, member_user_ids, created_at, updated_at)
         VALUES (:public_id, :title, :manager, :created_by, :member_ids, :created_at, :updated_at)'
    )->execute([
        'public_id' => $teamPublicId,
        'title' => 'Команда разработки',
        'manager' => $userId,
        'created_by' => $userId,
        'member_ids' => json_encode([(string)$userId], JSON_UNESCAPED_UNICODE),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Demo project
    $projectPublicId = 'prj_' . strtoupper(bin2hex(random_bytes(8)));
    $pdo->prepare(
        'INSERT INTO projects (public_id, title, description, status_code, priority_code, manager_user_id, team_public_id, created_by_user_id, created_at, updated_at)
         VALUES (:public_id, :title, :description, :status_code, :priority_code, :manager, :team, :created_by, :created_at, :updated_at)'
    )->execute([
        'public_id' => $projectPublicId,
        'title' => 'Demo Project',
        'description' => 'Демонстрационный проект для знакомства с системой',
        'status_code' => 'active',
        'priority_code' => 'normal',
        'manager' => $userId,
        'team' => $teamPublicId,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Demo tasks
    $tasks = [
        [
            'title' => 'Изучить интерфейс системы',
            'description' => 'Ознакомьтесь с основными разделами: проекты, задачи, канбан-доска, диаграмма Ганта.',
            'status_code' => 'new',
            'priority_code' => 'high',
        ],
        [
            'title' => 'Настроить рабочее пространство',
            'description' => 'Создайте свою команду, пригласите коллег и настройте уведомления.',
            'status_code' => 'in_progress',
            'priority_code' => 'normal',
        ],
        [
            'title' => 'Создать первый проект',
            'description' => 'Создайте проект, добавьте задачи, назначьте исполнителей и установите сроки.',
            'status_code' => 'done',
            'priority_code' => 'normal',
        ],
    ];

    $taskInsert = $pdo->prepare(
        'INSERT INTO tasks (public_id, project_id, title, description, status_code, priority_code, assignee_user_id, creator_user_id, created_at, updated_at)
         VALUES (:public_id, :project_id, :title, :description, :status_code, :priority_code, :assignee, :creator, :created_at, :updated_at)'
    );

    $projectIdStmt = $pdo->prepare('SELECT id FROM projects WHERE public_id = :public_id');
    $projectIdStmt->execute(['public_id' => $projectPublicId]);
    $projectId = (int)$projectIdStmt->fetchColumn();

    foreach ($tasks as $task) {
        $publicId = 'tsk_' . strtoupper(bin2hex(random_bytes(8)));
        $taskInsert->execute([
            'public_id' => $publicId,
            'project_id' => $projectId,
            'title' => $task['title'],
            'description' => $task['description'],
            'status_code' => $task['status_code'],
            'priority_code' => $task['priority_code'],
            'assignee' => $userId,
            'creator' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function finalizeInstall(PDO $pdo): void
{
    $now = gmdate('Y-m-d H:i:s');
    $count = 0;
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM install_state')->fetchColumn();
    } catch (\Throwable $e) {
        error_log('[Install::finalizeInstall] DB query failed: ' . $e->getMessage());
        $count = 0;
    }
    if ($count === 0) {
        $pdo->prepare(
            'INSERT INTO install_state (installed_at, version, payload) VALUES (:installed_at, :version, :payload)'
        )->execute([
            'installed_at' => $now,
            'version' => INSTALL_VERSION,
            'payload' => json_encode(['method' => 'web_installer'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    $storageBase = rtrim(getenv('CRM_STORAGE_BASE') ?: STORAGE_BASE_DEFAULT, '/');
    $dirs = [$storageBase, $storageBase . '/temp'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            error_log('[Installer] Cannot create directory: ' . $dir);
            throw new RuntimeException('Storage directory is not writable.');
        }
    }

    $storageLock = $storageBase . '/install.lock';
    $lockHandle = @fopen($storageLock, 'x');
    if ($lockHandle === false) {
        error_log('[Installer] Cannot write lock file: ' . $storageLock);
        throw new RuntimeException('Cannot write installation lock file. Check file permissions.');
    }
    fwrite($lockHandle, $now);
    fclose($lockHandle);

    $lockHandle2 = @fopen(LOCK_FILE_PATH, 'x');
    if ($lockHandle2 === false) {
        error_log('[Installer] Cannot write lock file: ' . LOCK_FILE_PATH);
        throw new RuntimeException('Cannot write installation lock file. Check file permissions.');
    }
    fwrite($lockHandle2, $now);
    fclose($lockHandle2);

    $storageGitignore = $storageBase . '/.gitignore';
    if (!is_file($storageGitignore)) {
        file_put_contents($storageGitignore, "*\n!.gitignore\n!.htaccess\n!.keep\n");
    }
    $storageHtaccess = $storageBase . '/.htaccess';
    if (!is_file($storageHtaccess)) {
        file_put_contents($storageHtaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    // Generate the updater recovery key. Only its hash is stored on disk; the
    // plain value is kept in the session for the one-time success screen and is
    // never written anywhere else. If the hash file already exists (reinstall
    // over an existing installation), keep the existing key.
    $updatesDir = $storageBase . '/updates';
    if (!is_dir($updatesDir) && !@mkdir($updatesDir, 0755, true)) {
        error_log('[Installer] Cannot create updates directory: ' . $updatesDir);
        throw new RuntimeException('Storage directory is not writable.');
    }
    $recoveryHashFile = $updatesDir . '/recovery_key.hash';
    if (!is_file($recoveryHashFile)) {
        try {
            $recoveryKey = generateRandomHex(16);
            if (@file_put_contents($recoveryHashFile, password_hash($recoveryKey, PASSWORD_DEFAULT)) !== false) {
                @chmod($recoveryHashFile, 0640);
                $_SESSION['install_recovery_key'] = $recoveryKey;
            }
        } catch (\Throwable $e) {
            error_log('[Installer] Recovery key generation failed: ' . $e->getMessage());
        }
    }
}

// ============================================================================
// Part 5: AJAX Action Handling
// ============================================================================

$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_ajax']));

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');

    if (!csrfCheck()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $substep = (int)($_POST['substep'] ?? 0);
    $installData = $_SESSION['install_data'] ?? [];

    if ($action === 'install' && !empty($installData)) {
        try {
            if (hasBlockingPreflightFailure()) {
                throw new RuntimeException(t('requirements') . ': ' . t('requirement_fail'));
            }
            // Re-read the install data from session
            $results = [];

            // Substep 1: Write .env
            if ($substep === 1 || $substep === 0) {
                if (!writeEnvFile($installData)) {
                    echo json_encode(['success' => false, 'substep' => 1, 'message' => t('env_write_error')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if ($substep === 1) {
                    echo json_encode(['success' => true, 'substep' => 1, 'message' => t('step_write_env')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $env = [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $installData['db_host'] ?? '127.0.0.1',
                'DB_PORT' => $installData['db_port'] ?? '3306',
                'DB_DATABASE' => $installData['db_database'] ?? '',
                'DB_USERNAME' => $installData['db_username'] ?? '',
                'DB_PASSWORD' => $installData['db_password'] ?? '',
                'DB_CHARSET' => 'utf8mb4',
            ];
            $pdo = getPdoConnection($env);

            // Substep 2: Create tables
            if ($substep === 2 || $substep === 0) {
                $tableErrors = createDatabaseTables($pdo, 'mysql');
                if (empty($tableErrors)) {
                    $tableErrors = runDatabaseMigrations($pdo, 'mysql');
                }
                if (!empty($tableErrors)) {
                    echo json_encode(['success' => false, 'substep' => 2, 'message' => t('table_create_error') . ': ' . implode('; ', array_slice($tableErrors, 0, 3))], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if ($substep === 2) {
                    echo json_encode(['success' => true, 'substep' => 2, 'message' => t('step_create_tables')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Substep 3: Seed dictionaries
            if ($substep === 3 || $substep === 0) {
                seedDictionaries($pdo);
                if ($substep === 3) {
                    echo json_encode(['success' => true, 'substep' => 3, 'message' => t('step_seed_data')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Substep 4: Create admin
            if ($substep === 4 || $substep === 0) {
                $adminUser = createAdminUser($pdo, $installData);
                $_SESSION['install_admin'] = $adminUser;
                if ($substep === 4) {
                    echo json_encode(['success' => true, 'substep' => 4, 'message' => t('step_create_admin')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Substep 5: Demo data + finalize
            if ($substep === 5 || $substep === 0) {
                $adminUser = $_SESSION['install_admin'] ?? [];
                if (!empty($adminUser)) {
                    createDemoData($pdo, $adminUser);
                } else {
                    $adminUser = createAdminUser($pdo, $installData);
                    $_SESSION['install_admin'] = $adminUser;
                    createDemoData($pdo, $adminUser);
                }
                finalizeInstall($pdo);
                $updateNotice = installUpdateNotice($installData);
                $_SESSION['install_update_notice'] = $updateNotice;
                $_SESSION['install_done'] = true;
                $_SESSION['install_admin'] = $adminUser;
                $_SESSION['install_credentials'] = [
                    'login' => $installData['admin_login'] ?? $installData['admin_email'] ?? 'admin',
                    'password' => $installData['admin_password'] ?? '',
                    'name' => $installData['admin_name'] ?? 'Administrator',
                ];
                echo json_encode([
                    'success' => true,
                    'substep' => 5,
                    'message' => t('install_success'),
                    'done' => true,
                    'update_notice' => $updateNotice,
                    'credentials' => [
                        'login' => $installData['admin_login'] ?? $installData['admin_email'] ?? 'admin',
                        'password' => $installData['admin_password'] ?? '',
                        'name' => $installData['admin_name'] ?? 'Administrator',
                    ],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // If no substep specified, run all (single-shot for non-JS fallback)
            echo json_encode(['success' => true, 'done' => true, 'message' => t('install_success')], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Throwable $e) {
            error_log('[Install::install] ' . $e->getMessage());
            echo json_encode(['success' => false, 'substep' => $substep, 'message' => t('install_failed')], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Test connection AJAX
    if ($action === 'test_connection') {
        $driver = SUPPORTED_DB_DRIVER;
        $host = sanitizeInput($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $database = sanitizeInput($_POST['db_database'] ?? '');
        $username = sanitizeInput($_POST['db_username'] ?? '');
        $password = $_POST['db_password'] ?? '';

        $result = testDatabaseConnection($driver, $host, $port, $database, $username, $password);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// Part 6: Installed Check

// Reset stale session data when system is not actually installed
if (!isAlreadyInstalled() && ($_SESSION['install_done'] ?? false)) {
    $_SESSION['install_done'] = false;
    $_SESSION['install_data'] = [];
    $_SESSION['install_admin'] = [];
    $_SESSION['install_credentials'] = [];
    $_SESSION['install_update_notice'] = null;
}

if (isAlreadyInstalled()) {
    ?><!DOCTYPE html>
<html lang="<?php echo e($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <title><?php echo t('already_installed'); ?> — CRM</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:"Aptos","Segoe UI Variable","Segoe UI",system-ui,Arial,sans-serif;background:#f5f8f7;color:#111a19;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .install-container{width:100%;max-width:480px;animation:fadeIn 0.5s ease-out}
        @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .install-logo{display:flex;align-items:center;justify-content:center;gap:10px;font-size:2rem;font-weight:800;margin-bottom:16px}
        .install-logo img{width:36px;height:36px;flex-shrink:0}
        .install-card{background:#fff;border:1px solid #d7e2df;border-radius:8px;padding:32px;box-shadow:0 1px 2px rgba(17,26,25,.04);text-align:center}
        .install-card h1{font-size:1.5rem;margin-bottom:16px;font-weight:700}
        .installed-desc{color:#596966;margin-bottom:24px;line-height:1.6;font-size:0.9rem}
        .btn{display:inline-block;padding:14px 24px;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s;border:none}
        .btn-primary{background:#0f8f72;color:#fff}.btn-primary:hover{background:#0b725c}
        .btn-block{width:100%;text-align:center}
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-logo"><img src="assets/icons/icon-192.png" alt="">CRM</div>
            <h1><?php echo t('already_installed'); ?></h1>
            <p class="installed-desc"><?php echo t('already_installed_desc'); ?></p>
            <a href="index.php?route=dashboard" class="btn btn-primary btn-block"><?php echo t('go_to_dashboard'); ?></a>
        </div>
    </div>
</body>
</html><?php
    exit;
}

// Check for partial install — .env exists but .install.lock doesn't
if (hasEnvConfig() && !is_file(LOCK_FILE_PATH) && canConnectFromEnv()) {
    if (hasInstallStateFromEnv()) {
        // SEC-007: Use fopen('x') for an atomic on POSIX: opens the file for
        // write, fails if it already exists. This eliminates the race between
        // the is_file() check on line ~6 and a possible concurrent writer.
        // file_put_contents() is not atomic and would silently overwrite an
        // existing lock created by a parallel install.
        $lockHandle = @fopen(LOCK_FILE_PATH, 'x');
        if ($lockHandle !== false) {
            fwrite($lockHandle, gmdate('Y-m-d H:i:s'));
            fclose($lockHandle);
        } else {
            // Either the lock was created concurrently (system is installed) or
            // the directory is unwritable. Either way we redirect to the dashboard
            // — but log so an operator can distinguish the two cases.
            error_log('[Installer] Could not acquire lock at ' . LOCK_FILE_PATH
                . ' (existing=' . (is_file(LOCK_FILE_PATH) ? 'yes' : 'no') . ')');
        }
        redirectToDashboard();
    }
}

// ============================================================================
// Part 7: Form Processing (POST for steps 1-3)
// ============================================================================

$errors = [];
$formData = $_SESSION['install_data'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAjax) {
    if (!csrfCheck()) {
        $errors[] = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        session_regenerate_id(true);
        $step = (int)($_POST['step'] ?? 1);

        if ($step === 1) {
            if (hasBlockingPreflightFailure()) {
                $errors[] = t('requirements') . ': ' . t('requirement_fail');
            }
            $formData['db_driver'] = SUPPORTED_DB_DRIVER;
            $formData['db_host'] = sanitizeInput($_POST['db_host'] ?? '127.0.0.1');
            $formData['db_port'] = (int)($_POST['db_port'] ?? 3306);
            $formData['db_database'] = sanitizeInput($_POST['db_database'] ?? '');
            $formData['db_username'] = sanitizeInput($_POST['db_username'] ?? '');
            $formData['db_password'] = $_POST['db_password'] ?? '';

            if ($formData['db_host'] === '') $errors[] = t('host') . ': ' . t('required_field');
            if ($formData['db_database'] === '') $errors[] = t('database') . ': ' . t('required_field');
            if ($formData['db_username'] === '') $errors[] = t('username') . ': ' . t('required_field');

            if (empty($errors)) {
                $testResult = testDatabaseConnection(
                    $formData['db_driver'],
                    $formData['db_host'],
                    $formData['db_port'],
                    $formData['db_database'],
                    $formData['db_username'],
                    $formData['db_password']
                );

                if (!$testResult['success']) {
                    $errors[] = $testResult['message'];
                } else {
                    $_SESSION['install_data'] = $formData;
                    $step = 2;
                }
            }

        } elseif ($step === 2) {
            $formData = $_SESSION['install_data'] ?? [];
            $formData['site_url'] = sanitizeInput($_POST['site_url'] ?? '');
            $formData['timezone'] = sanitizeInput($_POST['timezone'] ?? 'Europe/Moscow');
            $formData['admin_login'] = sanitizeInput($_POST['admin_login'] ?? 'admin');
            $formData['admin_email'] = sanitizeInput($_POST['admin_email'] ?? '');
            $formData['admin_password'] = $_POST['admin_password'] ?? '';
            $formData['admin_password_confirm'] = $_POST['admin_password_confirm'] ?? '';
            $formData['admin_name'] = sanitizeInput($_POST['admin_name'] ?? '');
            $formData['lang'] = $lang;

            if ($formData['site_url'] === '') $errors[] = t('site_url') . ': ' . t('required_field');
            if ($formData['admin_email'] === '' || !filter_var($formData['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = t('admin_email') . ': ' . t('invalid_email');
            // L-1: client-side validation matches server-side (12+ chars + complexity)
            if (strlen($formData['admin_password']) < 12
                || !preg_match('/[A-Z]/', $formData['admin_password'])
                || !preg_match('/[a-z]/', $formData['admin_password'])
                || !preg_match('/[0-9]/', $formData['admin_password'])
                || !preg_match('/[^a-zA-Z0-9]/', $formData['admin_password'])
            ) {
                $errors[] = t('admin_password') . ': ' . t('password_min_length');
            }
            if ($formData['admin_password'] !== $formData['admin_password_confirm']) $errors[] = t('passwords_mismatch');
            if ($formData['admin_name'] === '') $formData['admin_name'] = 'Administrator';

            if (empty($errors)) {
                if (!isset($formData['app_key'])) {
                    $formData['app_key'] = generateRandomHex(32);
                    $formData['csrf_key'] = generateRandomHex(32);
                    $formData['webhook_key'] = generateRandomHex(32);
                    $formData['ai_key'] = generateRandomHex(32);
                    $formData['local_secret'] = generateRandomHex(32);
                    $formData['cron_secret'] = generateRandomHex(32);
                }
                $_SESSION['install_data'] = $formData;
                $step = 3;
            }

        } elseif ($step === 3) {
            $formData = $_SESSION['install_data'] ?? [];
            $formData['app_key'] = sanitizeInput($_POST['app_key'] ?? '');
            $formData['csrf_key'] = sanitizeInput($_POST['csrf_key'] ?? '');
            $formData['webhook_key'] = sanitizeInput($_POST['webhook_key'] ?? '');
            $formData['ai_key'] = sanitizeInput($_POST['ai_key'] ?? '');

            if ($formData['app_key'] === '') $errors[] = t('app_key') . ': ' . t('required_field');
            if ($formData['csrf_key'] === '') $errors[] = t('csrf_key') . ': ' . t('required_field');

            if (empty($errors)) {
                $_SESSION['install_data'] = $formData;
                $step = 4;
            }

        } elseif ($step === 4) {
            // Non-JS fallback: run all steps synchronously
            $installData = $_SESSION['install_data'] ?? [];
            if (empty($installData)) {
                $errors[] = 'No installation data found. Please start over.';
                $step = 1;
            } else {
                try {
                    if (!writeEnvFile($installData)) {
                        throw new RuntimeException(t('env_write_error'));
                    }

                    $env = [
                        'DB_CONNECTION' => $installData['db_driver'] ?? 'mysql',
                        'DB_HOST' => $installData['db_host'] ?? '127.0.0.1',
                        'DB_PORT' => $installData['db_port'] ?? '3306',
                        'DB_DATABASE' => $installData['db_database'] ?? '',
                        'DB_USERNAME' => $installData['db_username'] ?? '',
                        'DB_PASSWORD' => $installData['db_password'] ?? '',
                        'DB_CHARSET' => 'utf8mb4',
                    ];
                    $pdo = getPdoConnection($env);

                    $tableErrors = createDatabaseTables($pdo, 'mysql');
                    if (empty($tableErrors)) {
                        $tableErrors = runDatabaseMigrations($pdo, 'mysql');
                    }
                    if (!empty($tableErrors)) {
                        throw new RuntimeException(t('table_create_error') . ': ' . implode('; ', array_slice($tableErrors, 0, 3)));
                    }

                    seedDictionaries($pdo);
                    $adminUser = createAdminUser($pdo, $installData);
                    createDemoData($pdo, $adminUser);
                    finalizeInstall($pdo);

                    $_SESSION['install_update_notice'] = installUpdateNotice($installData);
                    $_SESSION['install_done'] = true;
                    $_SESSION['install_admin'] = $adminUser;
                    $_SESSION['install_credentials'] = [
                        'login' => $installData['admin_login'] ?? $installData['admin_email'] ?? 'admin',
                        'password' => $installData['admin_password'] ?? '',
                        'name' => $installData['admin_name'] ?? 'Administrator',
                    ];

                } catch (Throwable $e) {
                    error_log('[Install::demoData] ' . $e->getMessage());
                    $errors[] = t('install_failed');
                    $step = 4;
                }
            }
        }
    }
}

// ============================================================================
// Part 8: Determine Current Step
// ============================================================================

$currentStep = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$currentStep = max(1, min(4, $currentStep));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAjax && empty($errors)) {
    if (isset($step)) {
        $currentStep = $step;
    }
}

if (($_SESSION['install_done'] ?? false) && $currentStep !== 4) {
    $currentStep = 4;
}

// Guard: redirect to step 1 if session data is missing for later steps
if ($currentStep >= 2 && empty($_SESSION['install_data']) && !($_SESSION['install_done'] ?? false)) {
    $currentStep = 1;
    $errors[] = 'Session expired. Please start the installation again.';
}

$steps = [
    1 => t('step_db'),
    2 => t('step_site'),
    3 => t('step_keys'),
    4 => t('step_install'),
];

// ============================================================================
// Part 9: Language Switch Handler
// ============================================================================

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en', 'zh', 'es', 'pt', 'de', 'fr'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    $lang = $_GET['lang'];
    $redirectPath = strtok($_SERVER['REQUEST_URI'], '?');
    $redirectPath .= isset($_GET['step']) ? '?step=' . max(1, min(4, (int)$_GET['step'])) : '';
    header('Location: ' . $redirectPath, true, 302);
    exit;
}

// ============================================================================
// HTML Template
// ============================================================================

?><!DOCTYPE html>
<html lang="<?php echo e($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <title><?php echo t('title'); ?> — CRM</title>
    <style><?php
// Embedded CSS
$css = <<<'CSS'
:root {
    --bg-start: #f5f8f7;
    --bg-end: #eef4f2;
    --card-bg: #ffffff;
    --card-border: #d7e2df;
    --text-primary: #111a19;
    --text-secondary: #596966;
    --text-muted: #7a8986;
    --accent: #0f8f72;
    --accent-hover: #0b725c;
    --success: #16834a;
    --error: #b54038;
    --warning: #9a6500;
    --input-bg: #ffffff;
    --input-border: #cfd9d5;
    --input-focus: rgba(15,143,114,0.18);
    --surface-soft: #f8fbfa;
    --radius: 8px;
    --radius-sm: 6px;
    --shadow: 0 1px 2px rgba(17,26,25,0.04);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Aptos", "Segoe UI Variable", "Segoe UI", system-ui, "Arial", sans-serif;
    background: linear-gradient(180deg, var(--bg-start) 0%, var(--bg-end) 100%);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 44px 20px;
    line-height: 1.6;
}

body.installed-page {
    padding-top: 60px;
}

.install-container {
    width: 100%;
    max-width: 760px;
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.install-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-align: center;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: 0;
    margin-bottom: 8px;
    color: var(--text-primary);
}
.install-logo img {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
}

.install-subtitle {
    text-align: center;
    color: var(--text-secondary);
    margin-bottom: 32px;
    font-size: 0.95rem;
}

.lang-switch {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 24px;
}

.lang-btn {
    padding: 6px 16px;
    border-radius: 999px;
    border: 1px solid var(--input-border);
    background: var(--input-bg);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
    text-decoration: none;
}

.lang-btn:hover {
    border-color: var(--accent);
    color: var(--text-primary);
}

.lang-btn.active {
    background: #e9f6f2;
    border-color: var(--accent);
    color: var(--accent-hover);
}

.steps {
    display: flex;
    justify-content: center;
    margin-bottom: 32px;
    position: relative;
}

.steps::before {
    content: '';
    position: absolute;
    top: 18px;
    left: calc(50% - 160px);
    width: 320px;
    height: 2px;
    background: var(--card-border);
    z-index: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    width: 100px;
}

.step-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.3s;
    border: 1px solid var(--input-border);
    background: var(--card-bg);
    color: var(--text-muted);
}

.step.completed .step-circle {
    background: #edf7f3;
    border-color: var(--success);
    color: var(--success);
}

.step.active .step-circle {
    background: #e9f6f2;
    border-color: var(--accent);
    color: var(--accent-hover);
    box-shadow: none;
}

.step-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
    max-width: 90px;
}

.step.active .step-label {
    color: var(--accent);
}

.step.completed .step-label {
    color: var(--success);
}

.install-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    padding: 30px;
    box-shadow: var(--shadow);
}

.install-card h2 {
    font-size: 1.25rem;
    margin-bottom: 24px;
    font-weight: 600;
    color: var(--text-primary);
}

.install-card h1 {
    font-size: 1.5rem;
    margin-bottom: 16px;
    font-weight: 700;
    text-align: center;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.form-group label .optional {
    color: var(--text-muted);
    font-weight: 400;
}

.form-control {
    width: 100%;
    min-height: 44px;
    padding: 10px 13px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}

.form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--input-focus);
}

.form-control::placeholder {
    color: var(--text-muted);
}

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 480px) {
    .form-row { grid-template-columns: 1fr; }
    .install-card { padding: 20px; }
    .steps::before { width: 220px; left: calc(50% - 110px); }
    .step { width: 70px; }
}

.password-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.8rem;
    padding: 4px 8px;
}

.password-toggle:hover {
    color: var(--text-primary);
}

.btn {
    min-height: 42px;
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    background: var(--accent-hover);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    background: #fff;
    color: var(--text-secondary);
    border: 1px solid var(--input-border);
}

.btn-secondary:hover {
    background: var(--input-bg);
    color: var(--text-primary);
}

.btn-block {
    width: 100%;
    justify-content: center;
    padding: 14px 24px;
    font-size: 1rem;
}

.btn-group {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.btn-group.between {
    justify-content: space-between;
}

.btn-icon {
    padding: 8px 16px;
    font-size: 0.85rem;
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.alert-error {
    background: #fff4f5;
    border: 1px solid #efc7c4;
    color: var(--error);
}

.alert-success {
    background: #edf7f3;
    border: 1px solid #bfe1d2;
    color: var(--success);
}

.alert-info {
    background: #f6faf8;
    border: 1px solid var(--card-border);
    color: var(--text-secondary);
}

.alert-warning {
    background: #fff9ed;
    border: 1px solid #efd6a8;
    color: #7a4b00;
}

.test-result {
    margin-top: 8px;
    font-size: 0.85rem;
}

.test-result.success {
    color: var(--success);
}

.test-result.error {
    color: var(--error);
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 24px;
}

.summary-item {
    padding: 12px;
    background: var(--surface-soft);
    border-radius: var(--radius-sm);
    border: 1px solid var(--input-border);
}

.summary-item.full {
    grid-column: 1 / -1;
}

.summary-item .label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.summary-item .value {
    font-size: 0.9rem;
    color: var(--text-primary);
    word-break: break-all;
}

.install-progress {
    margin: 24px 0;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: var(--input-border);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 16px;
}

.progress-fill {
    height: 100%;
    background: var(--accent);
    border-radius: 3px;
    transition: width 0.5s ease;
    width: 0%;
}

.install-steps {
    list-style: none;
}

.install-steps li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.install-steps li .step-icon {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.install-steps li.done {
    color: var(--success);
}

.install-steps li.active-step {
    color: var(--accent);
}

.install-steps li.error-step {
    color: var(--error);
}

.installed-desc {
    color: var(--text-secondary);
    text-align: center;
    margin-bottom: 24px;
    line-height: 1.6;
}

.credential-box {
    background: #edf7f3;
    border: 1px solid #bfe1d2;
    border-radius: var(--radius-sm);
    padding: 20px;
    margin-top: 20px;
}

.credential-box h3 {
    color: var(--success);
    margin-bottom: 12px;
    font-size: 1rem;
}

.credential-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.9rem;
}

.credential-row .key {
    color: var(--text-secondary);
}

.credential-row .val {
    color: var(--text-primary);
    font-weight: 500;
}

.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.driver-select {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    margin-bottom: 18px;
}

.driver-option {
    padding: 10px;
    text-align: center;
    border: 1px solid var(--input-border);
    border-radius: var(--radius-sm);
    background: var(--input-bg);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.driver-option:hover {
    border-color: var(--accent);
}

.driver-option input {
    display: none;
}

.driver-option.selected {
    border-color: var(--accent);
    background: #e9f6f2;
    color: var(--accent-hover);
}

.preflight-list {
    display: grid;
    gap: 8px;
    margin: 0 0 20px;
}

.preflight-item {
    display: grid;
    grid-template-columns: 22px 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border: 1px solid var(--card-border);
    border-radius: var(--radius-sm);
    background: var(--surface-soft);
    color: var(--text-secondary);
    font-size: 0.88rem;
}

.preflight-item.ok .preflight-mark { color: var(--success); }
.preflight-item.fail .preflight-mark { color: var(--error); }
.preflight-detail { color: var(--text-muted); font-size: 0.78rem; text-align: right; }
CSS;
echo $css;
?></style>
</head>
<body>
<div class="install-container">

    <div class="install-logo"><img src="assets/icons/icon-192.png" alt="">CRM</div>
    <div class="install-subtitle"><?php echo t('title'); ?> v<?php echo INSTALL_VERSION; ?></div>

    <div class="lang-switch">
        <?php foreach (['ru' => 'Русский', 'en' => 'English', 'zh' => '中文', 'es' => 'Español', 'de' => 'Deutsch', 'fr' => 'Français', 'pt' => 'Português'] as $code => $label): ?>
        <a href="?lang=<?php echo $code; ?><?php echo $currentStep > 1 ? '&amp;step=' . $currentStep : ''; ?>" class="lang-btn<?php echo $lang === $code ? ' active' : ''; ?>"><?php echo e($label); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($currentStep < 4 && !($_SESSION['install_done'] ?? false)): ?>
    <div class="steps">
        <?php foreach ($steps as $num => $label):
            $cls = '';
            if ($num < $currentStep) $cls = 'completed';
            if ($num === $currentStep) $cls = 'active';
        ?>
        <div class="step <?php echo $cls; ?>">
            <div class="step-circle">
                <?php echo $num < $currentStep ? '&#10003;' : $num; ?>
            </div>
            <div class="step-label"><?php echo e($label); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="install-card">
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?php echo e($err); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($currentStep === 1): ?>
        <h2><?php echo t('step_db'); ?></h2>

        <form method="post" id="step1-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="step" value="1">

            <div class="alert alert-info"><?php echo t('mysql_only_note'); ?></div>

            <div class="preflight-list" aria-label="<?php echo e(t('requirements')); ?>">
                <?php foreach (getPreflightChecks() as $check): ?>
                <div class="preflight-item <?php echo !empty($check['ok']) ? 'ok' : 'fail'; ?>">
                    <span class="preflight-mark"><?php echo !empty($check['ok']) ? '&#10003;' : '&#10007;'; ?></span>
                    <span><?php echo e((string)$check['label']); ?></span>
                    <span class="preflight-detail"><?php echo e((string)$check['detail']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="driver-select" id="driver-select">
                <?php foreach (['mysql' => 'MySQL'] as $dCode => $dLabel): ?>
                <label class="driver-option selected" data-driver="<?php echo $dCode; ?>">
                    <input type="radio" name="db_driver" value="<?php echo $dCode; ?>" checked>
                    <?php echo $dLabel; ?>
                </label>
                <?php endforeach; ?>
            </div>

            <div id="db-fields-mysql" class="db-fields" style="display:<?php echo ($formData['db_driver'] ?? 'mysql') === 'sqlite' ? 'none' : 'block'; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo t('host'); ?></label>
                        <input type="text" name="db_host" class="form-control" value="<?php echo e($formData['db_host'] ?? '127.0.0.1'); ?>" placeholder="127.0.0.1">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('port'); ?></label>
                        <input type="number" name="db_port" class="form-control" value="<?php echo e($formData['db_port'] ?? '3306'); ?>" placeholder="3306">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('database'); ?></label>
                    <input type="text" name="db_database" class="form-control" value="<?php echo e($formData['db_database'] ?? ''); ?>" placeholder="crm_api">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo t('username'); ?></label>
                        <input type="text" name="db_username" class="form-control" value="<?php echo e($formData['db_username'] ?? ''); ?>" placeholder="root">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('password'); ?></label>
                        <div class="password-wrapper">
                            <input type="password" name="db_password" class="form-control password-input" value="<?php echo e($formData['db_password'] ?? ''); ?>">
                            <button type="button" class="password-toggle" onclick="togglePassword(this)"><?php echo t('show'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="db-fields-sqlite" class="db-fields" style="display:<?php echo ($formData['db_driver'] ?? 'mysql') === 'sqlite' ? 'block' : 'none'; ?>">
                <div class="form-group">
                    <label><?php echo t('sqlite_path'); ?></label>
                    <input type="text" class="form-control" value="<?php echo e(sqliteDatabasePath()); ?>" readonly>
                    <div style="font-size:0.8rem; color: var(--text-muted); margin-top:4px;"><?php echo t('connection_ok'); ?> &#10003;</div>
                </div>
            </div>

            <div class="btn-group between">
        <button type="button" class="btn btn-secondary" id="test-connection-btn">
            <span class="test-icon">&#9881;</span>
            <span class="test-text"><?php echo t('test_connection'); ?></span>
            <span class="test-spinner" style="display:none">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;vertical-align:middle">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
            </span>
        </button>
                <button type="submit" class="btn btn-primary" id="next-btn" <?php echo hasBlockingPreflightFailure() ? 'disabled' : ''; ?>><?php echo t('next'); ?> &#8594;</button>
            </div>
            <div id="test-result" class="test-result"></div>
        </form>

        <?php elseif ($currentStep === 2): ?>
        <h2><?php echo t('step_site'); ?></h2>

        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="step" value="2">

            <div class="form-group">
                <label><?php echo t('site_url'); ?></label>
                <input type="text" name="site_url" class="form-control" value="<?php echo e($formData['site_url'] ?? autoDetectSiteUrl()); ?>" placeholder="https://example.com">
            </div>

            <div class="form-group">
                <label><?php echo t('timezone'); ?></label>
                <select name="timezone" class="form-control">
                    <?php foreach (getTimezones() as $tz => $tzLabel): ?>
                    <option value="<?php echo e($tz); ?>" <?php echo ($formData['timezone'] ?? 'Europe/Moscow') === $tz ? 'selected' : ''; ?>><?php echo e($tzLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><?php echo t('admin_login'); ?></label>
                <input type="text" name="admin_login" class="form-control" value="<?php echo e($formData['admin_login'] ?? 'admin'); ?>" placeholder="admin">
            </div>

            <div class="form-group">
                <label><?php echo t('admin_name'); ?></label>
                <input type="text" name="admin_name" class="form-control" value="<?php echo e($formData['admin_name'] ?? 'Administrator'); ?>">
            </div>

            <div class="form-group">
                <label><?php echo t('admin_email'); ?></label>
                <input type="email" name="admin_email" class="form-control" value="<?php echo e($formData['admin_email'] ?? ''); ?>" placeholder="admin@example.com">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?php echo t('admin_password'); ?></label>
                    <div class="password-wrapper">
                        <input type="password" name="admin_password" class="form-control password-input" value="<?php echo e($formData['admin_password'] ?? ''); ?>" minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword(this)"><?php echo t('show'); ?></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_password_confirm'); ?></label>
                    <input type="password" name="admin_password_confirm" class="form-control" value="" minlength="8">
                </div>
            </div>

            <div class="btn-group between">
                <a href="?step=1" class="btn btn-secondary">&#8592; <?php echo t('back'); ?></a>
                <button type="submit" class="btn btn-primary"><?php echo t('next'); ?> &#8594;</button>
            </div>
        </form>

        <?php elseif ($currentStep === 3): ?>
        <h2><?php echo t('step_keys'); ?></h2>

        <form method="post" id="step3-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="step" value="3">

            <div class="form-group">
                <label><?php echo t('app_key'); ?></label>
                <input type="text" name="app_key" class="form-control mono" value="<?php echo e($formData['app_key'] ?? ''); ?>" readonly>
            </div>
            <div class="form-group">
                <label><?php echo t('csrf_key'); ?></label>
                <input type="text" name="csrf_key" class="form-control mono" value="<?php echo e($formData['csrf_key'] ?? ''); ?>" readonly>
            </div>
            <div class="form-group">
                <label><?php echo t('webhook_key'); ?> <span class="optional">(<?php echo t('optional'); ?>)</span></label>
                <input type="text" name="webhook_key" class="form-control mono" value="<?php echo e($formData['webhook_key'] ?? ''); ?>" readonly>
            </div>
            <div class="form-group">
                <label><?php echo t('ai_key'); ?> <span class="optional">(<?php echo t('optional'); ?>)</span></label>
                <input type="text" name="ai_key" class="form-control mono" value="<?php echo e($formData['ai_key'] ?? ''); ?>" readonly>
            </div>

            <button type="button" class="btn btn-secondary btn-icon" id="regenerate-btn">&#8635; <?php echo t('regenerate_all'); ?></button>

            <div class="btn-group between">
                <a href="?step=2" class="btn btn-secondary">&#8592; <?php echo t('back'); ?></a>
                <button type="submit" class="btn btn-primary"><?php echo t('next'); ?> &#8594;</button>
            </div>
        </form>

        <?php elseif ($currentStep === 4): ?>

        <?php if ($_SESSION['install_done'] ?? false && isAlreadyInstalled()): ?>
        <h1 style="color: var(--success);">&#10003;</h1>
        <h2><?php echo t('install_success'); ?></h2>
        <p style="color: var(--text-secondary); text-align: center; margin-bottom: 20px;"><?php echo t('install_success_desc'); ?></p>
        <?php if (!empty($_SESSION['install_update_notice'])): ?>
        <div class="alert alert-warning"><?php echo e((string)$_SESSION['install_update_notice']); ?></div>
        <?php endif; ?>

        <div class="credential-box">
            <h3><?php echo t('login_credentials'); ?></h3>
            <?php $creds = $_SESSION['install_credentials'] ?? ['login' => 'admin', 'name' => 'Administrator']; ?>
            <div class="credential-row">
                <span class="key"><?php echo t('url'); ?>:</span>
                <span class="val"><?php echo e(autoDetectSiteUrl()); ?>/index.php?route=login</span>
            </div>
            <div class="credential-row">
                <span class="key"><?php echo t('login_label'); ?>:</span>
                <span class="val"><?php echo e($creds['login']); ?></span>
            </div>
            <div class="credential-row">
                <span class="key"><?php echo t('admin_password'); ?>:</span>
                <span class="val"><?php echo e($creds['password'] ?? ''); ?></span>
            </div>
            <div class="credential-row">
                <span class="key"><?php echo t('admin_name'); ?>:</span>
                <span class="val"><?php echo e($creds['name']); ?></span>
            </div>
        </div>

        <?php if (!empty($_SESSION['install_recovery_key'])): ?>
        <div class="credential-box" style="margin-top: 16px; border-color: #fcd34d;">
            <h3><?php echo t('recovery_key_title'); ?></h3>
            <p style="font-size: .85rem; color: var(--text-secondary); margin-bottom: 10px;"><?php echo t('recovery_key_desc'); ?></p>
            <div class="credential-row">
                <span class="key"><?php echo t('recovery_key_value'); ?>:</span>
                <span class="val" style="font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .03em;"><?php echo e((string)$_SESSION['install_recovery_key']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="btn-group" style="margin-top: 24px;">
            <a href="index.php?route=login" class="btn btn-primary btn-block"><?php echo t('go_to_dashboard'); ?></a>
        </div>

        <?php else: ?>
        <h2><?php echo t('step_install'); ?></h2>
        <p style="color: var(--text-secondary); margin-bottom: 20px;"><?php echo t('summary'); ?>:</p>

        <div class="summary-grid">
            <div class="summary-item">
                <div class="label"><?php echo t('summary_driver'); ?></div>
                <div class="value"><?php echo e(strtoupper($formData['db_driver'] ?? 'mysql')); ?></div>
            </div>
            <div class="summary-item">
                <div class="label"><?php echo t('summary_db_name'); ?></div>
                <div class="value"><?php echo e(($formData['db_driver'] ?? '') === 'sqlite' ? basename(sqliteDatabasePath()) : ($formData['db_database'] ?? '')); ?></div>
            </div>
            <div class="summary-item">
                <div class="label"><?php echo t('summary_site_url'); ?></div>
                <div class="value"><?php echo e($formData['site_url'] ?? ''); ?></div>
            </div>
            <div class="summary-item">
                <div class="label"><?php echo t('summary_timezone'); ?></div>
                <div class="value"><?php echo e($formData['timezone'] ?? 'Europe/Moscow'); ?></div>
            </div>
            <div class="summary-item full">
                <div class="label"><?php echo t('summary_admin'); ?></div>
                <div class="value"><?php echo e($formData['admin_name'] ?? 'Administrator'); ?> &lt;<?php echo e($formData['admin_email'] ?? ''); ?>&gt;</div>
            </div>
        </div>

        <div class="alert alert-info"><?php echo e(t('update_check_notice')); ?></div>

        <form method="post" id="install-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="step" value="4">
            <input type="hidden" name="action" value="install">

            <div class="install-progress" id="install-progress" style="display:none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <ul class="install-steps" id="install-steps">
                    <li id="li-1"><span class="step-icon" id="icon-1">&#9679;</span> <?php echo t('step_write_env'); ?></li>
                    <li id="li-2"><span class="step-icon" id="icon-2">&#9679;</span> <?php echo t('step_create_tables'); ?></li>
                    <li id="li-3"><span class="step-icon" id="icon-3">&#9679;</span> <?php echo t('step_seed_data'); ?></li>
                    <li id="li-4"><span class="step-icon" id="icon-4">&#9679;</span> <?php echo t('step_create_admin'); ?></li>
                    <li id="li-5"><span class="step-icon" id="icon-5">&#9679;</span> <?php echo t('step_demo_data'); ?></li>
                </ul>
            </div>

            <div id="install-success" style="display:none;"></div>
            <div id="install-error" style="display:none;" class="alert alert-error"></div>

            <div class="btn-group between" id="install-buttons">
                <a href="?step=3" class="btn btn-secondary">&#8592; <?php echo t('back'); ?></a>
                <button type="submit" class="btn btn-primary" id="install-btn">
                    <?php echo t('install_now'); ?>
                </button>
            </div>
        </form>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>window.installerI18n = <?php echo json_encode($L[$lang] ?? $L['en'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script><?php
// Embedded JS
$js = <<<'JS'
(function() {
    var i18n = window.installerI18n || {};
    function tr(key, fallback) {
        return typeof i18n[key] === 'string' && i18n[key] !== '' ? i18n[key] : fallback;
    }

    function togglePassword(btn) {
        var input = btn.parentElement.querySelector('.password-input');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = tr('hide', 'Hide');
        } else {
            input.type = 'password';
            btn.textContent = tr('show', 'Show');
        }
    }

    window.togglePassword = togglePassword;

    // Driver selector
    var driverSelect = document.getElementById('driver-select');
    if (driverSelect) {
        var driverOptions = driverSelect.querySelectorAll('.driver-option');
        var dbFieldsMysql = document.getElementById('db-fields-mysql');
        var dbFieldsSqlite = document.getElementById('db-fields-sqlite');

        driverOptions.forEach(function(opt) {
            opt.addEventListener('click', function() {
                driverOptions.forEach(function(o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                opt.querySelector('input').checked = true;

                var driver = opt.getAttribute('data-driver');
                if (driver === 'sqlite') {
                    dbFieldsMysql.style.display = 'none';
                    dbFieldsSqlite.style.display = 'block';
                } else {
                    dbFieldsMysql.style.display = 'block';
                    dbFieldsSqlite.style.display = 'none';
                    // Update port default
                    var portInput = document.querySelector('input[name="db_port"]');
                    if (portInput && portInput.value === '') {
                        portInput.value = driver === 'pgsql' ? '5432' : '3306';
                    }
                }
            });
        });
    }

    // Test connection
    var testBtn = document.getElementById('test-connection-btn');
    if (testBtn) {
        testBtn.addEventListener('click', function() {
            var resultDiv = document.getElementById('test-result');
            resultDiv.textContent = '';
            resultDiv.className = 'test-result';

            var testText = testBtn.querySelector('.test-text');
            var iconSpan = testBtn.querySelector('.test-icon');
            var spinnerSpan = testBtn.querySelector('.test-spinner');
            var originalText = testText ? testText.textContent : '';

            var driver = document.querySelector('input[name="db_driver"]:checked');
            driver = driver ? driver.value : 'mysql';

            testBtn.disabled = true;
            if (iconSpan) iconSpan.style.display = 'none';
            if (spinnerSpan) spinnerSpan.style.display = 'inline';
            if (testText) testText.textContent = tr('testing', 'Testing...');

            var formData = new FormData();
            formData.append('_ajax', '1');
            formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
            formData.append('action', 'test_connection');
            formData.append('db_driver', driver);

            if (driver === 'sqlite') {
                formData.append('db_host', '127.0.0.1');
                formData.append('db_port', '0');
                formData.append('db_database', '');
                formData.append('db_username', '');
                formData.append('db_password', '');
            } else {
                formData.append('db_host', document.querySelector('input[name="db_host"]').value || '127.0.0.1');
                formData.append('db_port', document.querySelector('input[name="db_port"]').value || '3306');
                formData.append('db_database', document.querySelector('input[name="db_database"]').value || '');
                formData.append('db_username', document.querySelector('input[name="db_username"]').value || '');
                formData.append('db_password', document.querySelector('input[name="db_password"]').value || '');
            }

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        resultDiv.textContent = '\u2713 ' + data.message;
                        resultDiv.className = 'test-result success';
                    } else {
                        resultDiv.textContent = '\u2717 ' + data.message;
                        resultDiv.className = 'test-result error';
                    }
                })
                .catch(function(err) {
                    resultDiv.textContent = '\u2717 Network error: ' + err.message;
                    resultDiv.className = 'test-result error';
                })
                .finally(function() {
                    testBtn.disabled = false;
                    if (iconSpan) iconSpan.style.display = 'inline';
                    if (spinnerSpan) spinnerSpan.style.display = 'none';
                    if (testText) testText.textContent = originalText;
                });
        });
    }

    // Regenerate keys
    var regenBtn = document.getElementById('regenerate-btn');
    if (regenBtn) {
        regenBtn.addEventListener('click', function() {
            var inputs = document.querySelectorAll('#step3-form input[readonly]');
            inputs.forEach(function(inp) {
                var arr = new Uint8Array(32);
                crypto.getRandomValues(arr);
                var hex = Array.from(arr).map(function(b) { return b.toString(16).padStart(2, '0'); }).join('');
                inp.value = hex;
            });
        });
    }

    // Installation progress
    var installForm = document.getElementById('install-form');
    if (installForm) {
        installForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Confirmation dialog before starting installation
            var confirmMsg = tr('confirm_install', 'Start CRM installation? Make sure all settings are correct.');
            if (!confirm(confirmMsg)) {
                return;
            }

            var progressDiv = document.getElementById('install-progress');
            var installBtn = document.getElementById('install-btn');
            var installButtons = document.getElementById('install-buttons');
            var progressFill = document.getElementById('progress-fill');
            var csrfInput = document.querySelector('#install-form input[name="_csrf"]');

            progressDiv.style.display = 'block';
            installBtn.disabled = true;
            installBtn.innerHTML = '<span class="spinner"></span> ' + tr('installing', 'Installing...');

            var steps = [
                { num: 1, id: 'li-1' },
                { num: 2, id: 'li-2' },
                { num: 3, id: 'li-3' },
                { num: 4, id: 'li-4' },
                { num: 5, id: 'li-5' },
            ];

            var totalSteps = steps.length;

            function markStep(index, status) {
                var li = document.getElementById(steps[index].id);
                var icon = document.getElementById('icon-' + (index + 1));
                if (!li || !icon) return;
                li.classList.remove('active-step', 'done', 'error-step');
                if (status === 'done') {
                    li.classList.add('done');
                    icon.innerHTML = '&#10003;';
                } else if (status === 'active') {
                    li.classList.add('active-step');
                    icon.innerHTML = '<span class="spinner"></span>';
                } else if (status === 'error') {
                    li.classList.add('error-step');
                    icon.innerHTML = '&#10007;';
                }
            }

            function updateProgress(percent) {
                progressFill.style.width = percent + '%';
            }

            function runStep(index) {
                if (index >= totalSteps) {
                    // All done — success display is handled in the last step's response
                    installButtons.style.display = 'none';
                    return;
                }

                markStep(index, 'active');
                updateProgress(((index) / totalSteps) * 100);

                var formData = new FormData();
                formData.append('_ajax', '1');
                formData.append('_csrf', csrfInput.value);
                formData.append('action', 'install');
                formData.append('substep', String(index + 1));

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            markStep(index, 'done');
                            updateProgress(((index + 1) / totalSteps) * 100);

                            if (data.done) {
                                updateProgress(100);
                                showSuccess(data.credentials, data.update_notice);
                            } else {
                                // Get fresh CSRF token from success response
                                runStep(index + 1);
                            }
                        } else {
                            markStep(index, 'error');
                            showError(data.message || 'Unknown error');
                        }
                    })
                    .catch(function(err) {
                        markStep(index, 'error');
                        console.error('[Installer] network error', err);
                        showError(tr('network_error', 'Network error'));
                    });
            }

            function escapeHtml(value) {
                return String(value || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g, '&quot;');
            }

            function showSuccess(credentials, updateNotice) {
                var successDiv = document.getElementById('install-success');
                var errorDiv = document.getElementById('install-error');
                errorDiv.style.display = 'none';

                var name = credentials ? credentials.name : tr('admin_name', 'Administrator');
                var login = credentials ? credentials.login : 'admin';
                var pwd = credentials ? credentials.password : '';
                var noticeHtml = updateNotice ? '<div class="alert alert-warning">' + escapeHtml(updateNotice) + '</div>' : '';

                successDiv.innerHTML = '<h1 style="color: var(--success);">&#10003;</h1>' +
                    '<h2>' + tr('install_success', 'Installation complete!') + '</h2>' +
                    '<p style="color: #94a3b8; text-align: center; margin-bottom: 20px;">' +
                    tr('install_success_desc', 'The system has been installed successfully. Use the credentials below to log in.') +
                    '</p>' + noticeHtml +
                    '<div class="credential-box">' +
                    '<h3>' + tr('login_credentials', 'Login Credentials') + '</h3>' +
                    '<div class="credential-row"><span class="key">' + tr('login_label', 'Login') + ':</span><span class="val">' + escapeHtml(login) + '</span></div>' +
                    '<div class="credential-row"><span class="key">' + tr('password', 'Password') + ':</span><span class="val">' + escapeHtml(pwd) + '</span></div>' +
                    '<div class="credential-row"><span class="key">' + tr('admin_name', 'Name') + ':</span><span class="val">' + escapeHtml(name) + '</span></div>' +
                    '</div>' +
                    '<div class="btn-group" style="margin-top:24px;">' +
                    '<a href="index.php?route=login" class="btn btn-primary btn-block">' + tr('go_to_dashboard', 'Go to Dashboard') + '</a>' +
                    '</div>';

                successDiv.style.display = 'block';
            }

            function showError(message) {
                var errorDiv = document.getElementById('install-error');
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                installButtons.style.display = 'flex';
                installBtn.disabled = false;
                installBtn.innerHTML = tr('install_now', 'Install');
            }

            runStep(0);
        });
    }

})();
JS;
echo $js;
?></script>
</body>
</html>
