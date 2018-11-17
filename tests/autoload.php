<?php
      define('__ROOT__', dirname(dirname(__FILE__)));

      // PHP version check
      if (version_compare(PHP_VERSION,'7.2.10','<'))
      {
        throw new Exception('KKPA has not been tested with fewer PHP 7.2.10 ');
      }

      //required libraries
      if (!function_exists('curl_version'))
      {
          throw new Exception('KKPA requires cURL extension.');
      }

      /**
       * class autoloader for KKPA
       *
       * @param string $class The fully-qualified class name.
       * @return void
       */
       spl_autoload_register(function($class)
       {
         //project-specific namespace prefix
         $prefix = 'KKPA\\';

         // base directory for the namespace prefix
         $baseDir = __DIR__.'/../src/';

         //does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0)
        {
            //no move to the next registered autoloader
            return;
        }
        //get the relative class name
        $relative_class = substr($class, $len);
        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $baseDir . str_replace('\\', '/', $relative_class) . '.php';
        //if the file exists, require it
        if (file_exists($file))
        {
            require $file;
        }
       })
 ?>
