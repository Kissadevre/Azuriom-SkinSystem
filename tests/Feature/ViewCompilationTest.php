<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Tests\TestCase;

class ViewCompilationTest extends TestCase
{
    public function test_plugin_blade_views_compile_without_syntax_errors(): void
    {
        $views = glob(dirname(__DIR__, 2).'/resources/views/**/*.blade.php');
        $views = array_merge($views, glob(dirname(__DIR__, 2).'/resources/views/*.blade.php'));

        foreach ($views as $view) {
            $compiled = app('blade.compiler')->compileString(file_get_contents($view));

            $this->assertNotSame('', $compiled, basename($view).' should compile.');
        }
    }
}
