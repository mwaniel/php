<?php

// Set include path to look for classes in the models directory, then in the controllers directory
set_include_path(get_include_path() . PATH_SEPARATOR . 'models' . PATH_SEPARATOR . 'controllers');

// Register the autoload function to automatically include classes
spl_autoload_register(array('Controller', 'autoload'));


abstract class Controller
{
    const DSN_CONFIG_FILE = 'config/Database.ini';
    const DATABASE_SCHEMA_FILE = 'data/schema.sql';
    const INITIAL_DATA_FILE = 'data/initial_data.sql';
    const SESSION_NAME = 'SURVEYFORMAPP';
    const RUNTIME_EXCEPTION_VIEW = 'runtime_exception.php';

    protected $config;
    protected $dsn;
    protected $pdo;
    protected $viewVariables = array();

    /**
     * Get the filename of the view file containing HTML and PHP presentation logic
     * 
     * @return string returns the view filename
     */
    protected function getViewFilename()
    {
        return basename($_SERVER['SCRIPT_NAME']);
    }

    /**
     * Handle the page request
     *
     * @param array $request the page parameters from a form post or query string
     */
    abstract protected function handleRequest(&$request);

    /**
     * Handle an exception and display the error to the user
     *
     * @param Exception $e the Exception to be displayed
     */
    protected function handleError(Exception $e)
    {
        $this->assign('statusMessage', $e->getMessage());
    }

    /**
     * Assign a variable to be used in the view
     *
     * @param string $name the variable name
     * @param mixed $value the variable value
     */
    protected function assign($name, $value)
    {
        $this->viewVariables[$name] = $value;
    }

    /**
     * Display the view associated with the controller
     */
    protected function displayView($viewFilename)
    {
        chdir('views');

        if (! file_exists($viewFilename))
            throw new RuntimeException("Filename does not exist: $viewFilename");

        // Extract view variables into current scope
        extract($this->viewVariables);

        // Display the view
        require $viewFilename;
    }

    /**
     * Automatically load the necessary file for a given class
     *
     * @param string $class the class name to autoload
     */
    public static function autoload($class)
    {
        require $class . '.php';
        return true;
    }

    /**
     * Display the page - open the database, execute controller code, and display the view
     */
    public function display()
    {
        // Make sure requests and responses are utf-8
        header('Content-type: text/html; charset=utf-8');

        try
        {
            // Check to make sure required dependencies are installed
            $this->checkDependencies();

            // Open PDO database connection
            $this->openDatabase();

            // Handle the page request
            $this->handleRequest($_REQUEST);

            // Get view filename
            $viewFilename = $this->getViewFilename();

            // Display the view
            $this->displayView($viewFilename);
        }
        catch (RuntimeException $e)
        {
            $this->assign('statusMessage', $e->getMessage());
            $this->displayView(self::RUNTIME_EXCEPTION_VIEW);
        }
        catch (Exception $e)
        {
            // Handle exception
            $this->handleError($e);

            // Get view filename
            $viewFilename = $this->getViewFilename();

            // Display view
            if ($viewFilename)
                $this->displayView($viewFilename);
            else
                die($e->getMessage());
        }
    }

    /**
     * Start a new session with the session name defined in the 
     * SESSION_NAME class constant
     */
    protected function startSession()
    {
        session_name(self::SESSION_NAME);

        session_start();
    }

    /**
     * Get the current user sessions, or redirect to the login page
     */
    protected function getUserSession()
    {
        $this->startSession();

        if (!isset($_SESSION['login']))
            $this->redirect('login.php');

        return $_SESSION['login'];
    }

    /**
     * Redirect to another URL
     *
     * @param string $url the URL to redirect to
     */
    protected function redirect($url)
    {
        // Close session information
        if (session_id() != "")
            session_write_close();

        header("Location: $url");
        exit;
    }

    /**
     * Check for required dependencies and throw an exception if not all dependencies are found
     *
     * @throws RuntimeException if a required dependency is not found
     */
    protected function checkDependencies()
    {
        $missing = array();

        if (! extension_loaded('openssl'))
            $missing[] = 'openssl';

        if (! extension_loaded('pdo'))
            $missing[] = 'PDO';

        // Version 3.6.19 is required for foreign key support and cascade support
        if (! extension_loaded('sqlite3'))
            $missing[] = 'sqlite3 version 3.6.19 or higher';
        else
        {
            $versionArray = sqlite3::version();
            $versionString = $versionArray['versionString'];
            if (version_compare($versionString, '3.6.19', '<'))
                $missing[] = 'sqlite3 version 3.6.19 or higher';
        }

        if (! extension_loaded('pdo_sqlite'))
            $missing[] = 'pdo_sqlite';

        if (!empty($missing))
            throw new RuntimeException("The following PHP extensions are required:\n\n" . implode("\n", $missing));
    }

    /**
     * Open a PDO connection to the database and assign it to instance variable $pdo
     *
     * @throws RuntimeException if the database could not be opened
     */
    protected function openDatabase()
    {
        if (! file_exists(self::DSN_CONFIG_FILE))
            throw new RuntimeException('Database config file not found: ' . self::DSN_CONFIG_FILE);

        $databaseConfig = parse_ini_file(self::DSN_CONFIG_FILE);
        if (!isset($databaseConfig['dsn']))
            throw new RuntimeException("Database config parameter 'dsn' not found in config file: " . self::DSN_CONFIG_FILE);

        if (!isset($databaseConfig['filename']))
            throw new RuntimeException("Database config parameter 'filename' not found in config file: " . self::DSN_CONFIG_FILE);

        if (!is_writable(dirname($databaseConfig['filename'])))
            throw new RuntimeException('Data directory not writable by web server: ' . dirname($databaseConfig['filename']) . '/');

        if (!is_writable(dirname($databaseConfig['filename'])) || (file_exists($databaseConfig['filename']) && !is_writable($databaseConfig['filename'])))
            throw new RuntimeException('Database file not writable by web server: ' . $databaseConfig['filename']);

        try
        {
            $this->pdo = new PDO($databaseConfig['dsn']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');

            if (! $this->databaseTablesCreated())
                $this->createDatabaseTables();
        }
        catch (PDOException $e)
        {
            throw new RuntimeException('PDOException: ' . $e->getMessage());
        }
    }

    /**
     * Create database tables from SQL schema file
     */
    protected function createDatabaseTables()
    {
        if (! file_exists(self::DATABASE_SCHEMA_FILE))
            throw new RuntimeException("Database schema file not found: " . self::DATABASE_SCHEMA_FILE);

        // Create tables
        $sql = file_get_contents(self::DATABASE_SCHEMA_FILE);
        $this->pdo->exec($sql);

        // Load initial data
        $sql = file_get_contents(self::INITIAL_DATA_FILE);
        $this->pdo->exec($sql);
    }

    /**
     * Determine if database tables have already been created
     */
    protected function databaseTablesCreated()
    {
        $sql = "select count(*) from sqlite_master where type='table' and name='login'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_NUM);
        if ($row = $stmt->fetch())
        {
            if ($row[0] == 1)
                return true;
        }
        return false;
    }
}

?>
