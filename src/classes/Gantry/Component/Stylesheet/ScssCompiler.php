<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Component\Stylesheet;

use Gantry\Component\Filesystem\Folder;
use Gantry\Component\Stylesheet\Scss\CompiledScssFile;
use Gantry\Component\Stylesheet\Scss\Compiler;
use Gantry\Framework\Base\Gantry;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\PhpFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class ScssCompiler extends CssCompiler
{
    /**
     * @var string
     */
    public $type = 'scss';

    /**
     * @var string
     */
    public $name = 'SCSS';

    /**
     * @var Compiler
     */
    protected $compiler;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->compiler = new Compiler();

        if ($this->production) {
            $this->compiler->setFormatter('Leafo\ScssPhp\Formatter\Crunched');
        } else {
            $this->compiler->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
            $this->compiler->setLineNumberStyle(Compiler::LINE_COMMENTS);
        }
    }

    public function compile($in)
    {
        return $this->compiler->compile($in);
    }

    public function resetCache()
    {
    }

    /**
     * @param string $in    Filename without path or extension.
     * @return bool         True if the output file was saved.
     */
    public function compileFile($in)
    {
        $gantry = Gantry::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        $out = $this->getCssUrl($in);
        $path = $locator->findResource($out, true, true);

        // Set the lookup paths.
        $this->compiler->setBasePath($path);
        $this->compiler->setImportPaths([[$this, 'findImport']]);

        // Run the compiler.
        $this->compiler->setVariables($this->getVariables());
        $scss = '@import "' . $in . '.scss"';
        $css = $this->compiler->compile($scss);
        if (strpos($css, $scss) === 0) {
            $css = '/* ' . $scss . ' */';
        }

        if (!$this->production) {
            $warning = <<<WARN
/* GANTRY5 DEVELOPMENT MODE ENABLED.

   WARNING: This file is automatically generated by Gantry5. Any modifications to this file will be lost!

   For more information on modifying CSS, please read:

   http://docs.gantry.org/gantry5/configure/styles
   http://docs.gantry.org/gantry5/tutorials/adding-a-custom-style-sheet
 */
WARN;
            $css = $warning . "\n\n" . $css;
        }

        $file = File::instance($path);

        // Attempt to lock the file for writing.
        try {
            $file->lock(false);
        } catch (\Exception $e) {
            // Another process has locked the file; we will check this in a bit.
        }

        //TODO: Better way to handle double writing files at same time.
        if ($file->locked() === false) {
            // File was already locked by another process.
            return false;
        }

        $file->save($css);
        $file->unlock();

        $this->createMeta($out, md5($css));

        return true;
    }

    /**
     * @param string   $name       Name of function to register to the compiler.
     * @param callable $callback   Function to run when called by the compiler.
     * @return $this
     */
    public function registerFunction($name, callable $callback)
    {
        $this->compiler->registerFunction($name, $callback);

        return $this;
    }

    /**
     * @param string $name       Name of function to unregister.
     * @return $this
     */
    public function unregisterFunction($name)
    {
        $this->compiler->unregisterFunction($name);

        return $this;
    }


    /**
     * @internal
     */
    public function findImport($url)
    {
        $gantry = Gantry::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        // Ignore vanilla css and external requests.
        if (preg_match('/\.css$|^https?:\/\//', $url)) {
            return null;
        }

        // Try both normal and the _partial filename.
        $files = array($url, preg_replace('/[^\/]+$/', '_\0', $url));

        foreach ($this->paths as $base) {
            foreach ($files as $file) {
                if (!preg_match('|\.scss$|', $file)) {
                    $file .= '.scss';
                }
                if ($locator->findResource($base . '/' . $file)) {
                    return $base . '/' . $file;
                }
            }
        }

        return null;
    }
}
