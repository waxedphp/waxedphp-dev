<?php
namespace Waxedphp\Waxedphpdev;

use Waxedphp\Waxedphp\Plugin;
use MatthiasMullie\Minify;


class PluginDev extends Plugin {

  /**
   * @var int $cache_ttl
   */
  protected int $cache_ttl = 60 * 60;


  /**
  * get node js path
  *
  * @return string
  */
  public function getNodeJsPath(): string {
    return $this->nodeJsPath;
  }

  /**
  * set node js path
  *
  * @param string $nodeJsPath
  * @return Plugin
  */
  public function setNodeJsPath(string $nodeJsPath): Plugin {
    $rpath = realpath($nodeJsPath);
    if (!$rpath) throw new \Exception('Wrong NODE JS path.');
    $this->nodeJsPath = $rpath;
    return $this;
  }

  /**
  * uses
  *
  * @param array<string>|string $listing
  * @return Plugin
  */
  public function uses(array|string $listing): Plugin {
    $_uses_ = func_get_args();
    if (is_array($_uses_[0])) {
      $_uses_ = $_uses_[0];
    };
    foreach ($_uses_ as $k => $v) {
      $rr = $this->read($v);
      //print_r($rr);
      if ((is_array($rr)) && (!empty($rr))) {
        $this->_uses_[$v] = $v;
      } else {// still, put it there:
        $this->_uses_[$v] = $v;
        continue;
      }
      if (isset($rr['js']))
        $this->units['js'] = array_merge($this->units['js'], $rr['js']);
      if (isset($rr['css']))
        $this->units['css'] = array_merge($this->units['css'], $rr['css']);
    };
    ksort($this->_uses_);
    if (isset($this->_uses_['base'])) {
      $this->_uses_ = ['base'=>'base']+$this->_uses_;
    }
    if (isset($this->_uses_['design'])) {
      unset($this->_uses_['design']);
      $this->_uses_ = $this->_uses_+['design'=>'design'];
    }
    $this->_uses_ = array_keys($this->_uses_);
    $this->_prepare_build();
    return $this;
  }

  /**
  * _is_production
  *
  * @return bool
  protected function _is_production(): bool {
    return !$this->base->is_development();
  }
  */

  /**
  * _nocache
  *
  * @return bool
  */
  protected function _nocache(): bool {
    if ($this->cache) return true;
    return false;
  }

  /**
  * _prepare_build
  *
  * @return void
  */
  protected function _prepare_build(): void {
    if (!$this->writablePath) throw new \Exception('Writable path is not set.');
    $plug = implode('-', $this->_uses_);
    $path = $this->writablePath . '/' . $plug;
    //die($path);
    if (!is_dir($path)) {
      mkdir($path);
      $s = ''."\n";
      foreach ($this->_uses_ as $pkg) {
        $s .= 'import "' . $this->getRelativePath($path,$this->packagePath) . '/' . $pkg . '/loader.js"'."\n";
      }
      $s .= ''."\n";
      file_put_contents($path.'/loader.js', $s);
      $cmd = '#!/bin/bash";'."\n";
      $cmd .= 'cd "$(dirname "$0")";' . "\n";
      $cmd .= 'cd ../../;' . "\n";
      $cmd .= 'bash ./build.sh "' . $plug . '";'. "\n";

      /*
      $cmd .= 'cd "' . $this->packagePath . '/";'."\n";
      $cmd .= 'webpack --config webpack.config.js ';
      $cmd .= ' --entry="'.$path.'/loader.js" ';
      $cmd .= ' --output-path="'.$path.'"'."\n";
      */
      file_put_contents($path.'/build.sh', $cmd);
    };
  }

  /**
  * build
  *
  * @return array<mixed>
  */
  public function build(): array {
    if (!$this->writablePath) throw new \Exception('Writable path is not set.');
    $path = $this->writablePath . '/' . implode('-', $this->_uses_);
    $output = [];$result_code = null;
    exec('bash "' . $path . '/build.sh"', $output, $result_code);
    $output[] = 'result:' . $result_code;
    return $output;
  }

  /**
  * _inside
  *
  * @param string $plugin
  * @param string $file
  * @param string $ext
  * @return Plugin
  */
  protected function _inside(string $plugin, string $file, string $ext): Plugin {
    //print_r($plugin); print_r($file); print_r($ext);die();
    if (!preg_match('/^[a-z0-9\_]+$/i', $plugin)) {
      //echo 'plugin';
      return $this;
    };
    if (!preg_match('/^[a-zA-Z0-9\_\-\.\/]+$/i', $file)) {
      //echo 'file';
      return $this;
    };
    if (!preg_match('/^(js|json|css|eot|svg|ttf|woff|woff2|otf|png|jpg|jpeg|gif|map|dbg)$/i', $ext)) {
      //echo 'ext';
      return $this;
    };
    //print_r($this->packagePath . '' . $plugin . DIRECTORY_SEPARATOR . 'inside.php');die();
    $paths = $this->get_inside($plugin);
    foreach ($paths[$ext] as $p) {
      $s = str_replace('/', DIRECTORY_SEPARATOR, $p);
      $path1 = (String)$this->packagePath . '' . $plugin . $s;
      $path2 = (String)realpath($s);
      if (strpos($path1, $this->packagePath)!==0) {
        $path1 = '';
      };
      if (strpos($path2, $this->nodeJsPath)!==0) {
        $path2 = '';
      };
      echo '/' . '* ' .$path1 . ' *' . '/' . "\n";
      echo '/' . '* ' .$path2 . ' *' . '/' . "\n";
      if (($path1) && (is_dir($path1)) && (is_file($path1 . $file . '.' . $ext))) {
        $this->units[$ext][] = explode(DIRECTORY_SEPARATOR, $path1 . $file . '.' . $ext);
        break;
      } else if (($path2) && (is_dir($path2)) && (is_file($path2 . $file . '.' . $ext))) {
        $rpath = realpath($path2 . $file . '.' . $ext);
        if (!$rpath) throw new \Exception('Wrong NODE path.');
        if (!(strpos($rpath, $this->getNodeJsPath())===0)) throw new \Exception('Wrong NODE path.');
        $this->units[$ext][] = explode(DIRECTORY_SEPARATOR, $rpath);
        break;
      }
    };
    return $this;
  }

  /**
  * mode
  *
  * @param string $mode
  * @return Plugin
  */
  public function mode(string $mode): Plugin {
    $this->mode = $mode;
    return $this;
  }

  /**
  * get units
  *
  * @return array<mixed>
  */
  public function getUnits(): array {
    return $this->units;
  }

  /**
  * dispatch
  *
  * @param string $url
  * @param array<mixed> $params
  * @return void
  */
  public function dispatch(string $url, array $params = []): void {
    $bInside = false;
    $url = str_replace(ltrim($this->route, '/'), '', $url);
    $a = explode('/', $url);
    if (count($a) == 2) {
      $url = $a[1];
      $bInside = $a[0];
    } else if (count($a) == 3) {
      $url = $a[1] . '/' . $a[2];
      $bInside = $a[0];
    } else if (count($a) == 4) {
      $url = $a[1] . '/' . $a[2] . '/' . $a[3];
      $bInside = $a[0];
    };
    $ext_pos = strrpos($url, '.');
    if (!$ext_pos) throw new \Exception('Wrong extension.');
    $path = substr($url, 0, $ext_pos);
    $extension = substr($url, $ext_pos+1);

    if ($bInside) {

      $this->_inside($bInside, $path, $extension)->mode($extension);

    } else {
      if ($this->cache) {
        $cc = $this->cache->get($path . '.' . $extension);
        if ($cc) {
          echo $this->mode($extension)->GET($cc);
          return;
        };
      };
      $aPath = explode('-', $path);
      //print_r($uu);
      $this->uses($aPath)->mode($extension);
    }
    $content = $this->GET();
    //$this->_minify($content, $extension);
    if ($this->cache) {
      $this->cache->save($path . '.' . $extension, $content, $this->cache_ttl);
    };
    echo $content;
  }

  /**
  * dispatch2
  *
  * @param string $url
  * @param array<mixed> $params
  * @return void
  */
  public function dispatch2(string $url, array $params = []): string {
    $bInside = false;
    $url = str_replace(ltrim($this->route, '/'), '', $url);
    $a = explode('/', $url);
    if (count($a) == 2) {
      $url = $a[1];
      $bInside = $a[0];
    } else if (count($a) == 3) {
      $url = $a[1] . '/' . $a[2];
      $bInside = $a[0];
    } else if (count($a) == 4) {
      $url = $a[1] . '/' . $a[2] . '/' . $a[3];
      $bInside = $a[0];
    };
    $ext_pos = strrpos($url, '.');
    if (!$ext_pos) throw new \Exception('Wrong extension.');
    $path = substr($url, 0, $ext_pos);
    $extension = substr($url, $ext_pos+1);

    if ($bInside) {

      $this->_inside($bInside, $path, $extension)->mode($extension);

    } else {
      if ($this->cache) {
        $cc = $this->cache->get($path . '.' . $extension);
        if ($cc) {
          return $this->mode($extension)->GET($cc);
        };
      };
      $aPath = explode('-', $path);
      //print_r($uu);
      $this->uses($aPath)->mode($extension);
    }
    $content = $this->GET();
    //$this->_minify($content, $extension);
    if ($this->cache) {
      $this->cache->save($path . '.' . $extension, $content, $this->cache_ttl);
    };
    return $content;
  }

  /**
  * minify
  *
  * @param string $content
  * @param string $extension
  * @return void
  */
  private function _minify(string &$content, string $extension): void {
    $fn = '/dev/null';
    //if ($this->writablePath) {
    //  $fn = $this->writablePath . '/' . $path . '.' . $extension;
    //};
    switch ($extension) {
      case 'js':
        $minifier = new Minify\JS($content);
        $content = $minifier->minify($fn);
        break;
      case 'css':
        $minifier = new Minify\CSS($content);
        $content = $minifier->minify($fn);
        break;
    }
  }

  /**
  * js
  *
  * @return string
  */
  public function JS(): string {
    $s = '';
    switch ($this->mode) {
      case 'html': //$this->route .
        $s.='<script type="text/javascript" src="';
        $s.=$this->route;
        $s.=implode('-', $this->_uses_);
        $s.='.js';
        if ($this->_nocache()) {
          $s.='?d='.date('YmdHis');
        }
        $s.='" ></script>';
        return $s;
      case 'html-include':
        return '<script type="text/javascript" >' . $this->load('js', $this->units) . '</script>';
      case 'js':
        return $this->load('js', $this->units);
    }
    return $s;
  }

  /**
  * css
  *
  * @return string
  */
  public function CSS(): string {
    $s = '';
    switch ($this->mode) {
      case 'html'://$this->route .
        $s .= '<link rel="stylesheet" type="text/css" href="';
        $s .= $this->route;
        $s .= implode('-', $this->_uses_);
        $s .= '.css?';
        //$s .= $this->base->design->getStyleQuery();
        if ($this->_nocache()) {
          $s .= '&d='.date('YmdHis');
        }
        $s .= '" />';
        return $s;
      case 'html-include':
        return '<style>' . $this->load('css', $this->units) . '</style>';
      case 'css':
        return $this->load('css', $this->units);
    }
    return $s;
  }

  /**
  * get
  *
  * @param string $content
  * @return string
  */
  public function GET($content = ''): string {
    switch ($this->mode) {
      case 'js':
        header("Content-type: application/javascript");
        if ($content) return $content;
        return $this->load('js', $this->units);
      case 'json':
        header("Content-type: application/json");
        if ($content) return $content;
        return $this->load('json', $this->units);
      case 'css':
        header("Content-type: text/css");
        if ($content) return $content;
        return $this->load('css', $this->units);
      case 'eot':
        header("Content-type: font/ttf");
        if ($content) return $content;
        return $this->load('eot', $this->units);
      case 'ttf':
        header("Content-type: font/ttf");
        if ($content) return $content;
        return $this->load('ttf', $this->units);
      case 'woff':
        header("Content-type: font/woff");
        if ($content) return $content;
        return $this->load('woff', $this->units);
      case 'woff2':
        header("Content-type: font/woff2");
        if ($content) return $content;
        return $this->load('woff2', $this->units);
      case 'otf':
        header("Content-type: font/otf");
        if ($content) return $content;
        return $this->load('otf', $this->units);
      case 'svg':
        header("Content-type: image/svg+xml");
        if ($content) return $content;
        return $this->load('svg', $this->units);
      case 'gif':
        header("Content-type: image/gif");
        if ($content) return $content;
        return $this->load($this->mode, $this->units);
      case 'png':
        header("Content-type: image/png");
        if ($content) return $content;
        return $this->load($this->mode, $this->units);
      case 'jpg':
      case 'jpeg':
        header("Content-type: image/jpeg");
        if ($content) return $content;
        return $this->load($this->mode, $this->units);
      case 'map':
        header("Content-type: text/plain");
        if ($content) return $content;
        return $this->load($this->mode, $this->units);
      case 'dbg':
        header("Content-type: text/plain");
        if ($content) return $content;
        return print_r($this->units, true);
    }
    throw new \Exception('Extension .' . $this->mode . ' is not allowed.');
  }

  /**
  * read
  *
  * @param string $name
  * @return array<mixed>
  */
  public function read(string $name): array {
    if (!preg_match('/^[a-z0-9\_]+$/i', $name)) {
      throw new \Exception('Wrong plugin name.');
    };
    $ret = [];
    $dep = $this->get_dependency($name);
    if (empty($dep)) throw new \Exception('Plugin is not installed: ' . $name);
    foreach ($dep as $key => $val) {
      if (!in_array((string)$key, ['css', 'js'])) {
        if (is_string($val)) {
          $this->find_nonstandard_path($val, $ret);
        } else {
          //print_r($name);print_r($key);die();
        }
      } else if (count($dep[$key])) {
        foreach ($dep[$key] as $dp) {
          $this->find_standard_path($dp, $key, $ret);
        };
      }
    }
    //print_r($ret);
    return $ret;
  }

  /**
  * find_standard_path
  *
  * @param string $dp
  * @param string $key
  * @param array<mixed> $ret
  * @return void
  */
  function find_standard_path(string $dp, string $key, array &$ret): void {
    $pu = parse_url($dp);
    if ((!$pu)||(!$pu['path'])) throw new \Exception('Wrong path.');
    $path = $pu['path'];
    (isset($pu['query']))?$query=$pu['query']:$query=false;
    if (($query)&&($key=='css')) {
      if ($query!=$this->base->design->getStyle()) {
        return;
      };
    };
    $pi = pathinfo($path);
    if (isset($pi['extension'])) {
      $ext = $pi['extension'];
      if ($ext) {
        if (!isset($ret[$ext])) {
          $ret[$ext] = [];
        }
        $pp = preg_replace('/^\/js\/jam\//', '', $path);
        if (!$pp) throw new \Exception('Wrong path.');
        $ret[$ext][$pp] = explode('/', $pp);//$path . $t;
      }
    };
  }

  /**
  * find_nonstandard_path
  *
  * @param string $val
  * @param array<mixed> $ret
  * @return void
  */
  function find_nonstandard_path(string $val, array &$ret): void {
    $pu = parse_url($val);
    if ((!$pu)||(!$pu['path'])) throw new \Exception('Wrong path in dependencies.');
    $path = $pu['path'];
    $pi = pathinfo($path);
    if (isset($pi['extension'])) {
      $ext = $pi['extension'];
      if (in_array($ext, ['css', 'js'])) {
        if (!isset($ret[$ext])) {
          $ret[$ext] = [];
        }
        $pp = preg_replace('/^\/js\/jam\//', '', $path);
        if (!$pp) throw new \Exception('Wrong path.');
        $ret[$ext][$pp] = explode('/', $pp);// . $t;
      };
    };
  }

  /**
  * load
  *
  * @param string $key
  * @param array<mixed> $arr
  * @return string
  */
  public function load(string $key, array $arr): string {
    $s = '';$webpack = '';

    //print_r($arr);
    if ((is_array($arr)) && (isset($arr[$key]))) {
      foreach ($arr[$key] as $script) {
        $fname1 = $this->packagePath . implode(DIRECTORY_SEPARATOR, $script);
        $fname2 = implode(DIRECTORY_SEPARATOR, $script);
        //print_r($this->packagePath);
        //print_r($this->nodeJsPath);
        if (strpos($fname1, $this->packagePath)!==0) {
          $fname1 = '';
        };
        if (strpos($fname2, $this->nodeJsPath)!==0) {
          $fname2 = '';
        };
        echo '/' . '* 1: ' . $fname1 . '*' . '/'."\n";
        echo '/' . '* 2: ' . $fname2 . '*' . '/'."\n";
        if (($fname1) && (file_exists($fname1))) {
          $s .= file_get_contents($fname1) . "\n";
        } elseif (($fname2) && (file_exists($fname2))) {
          $s .= file_get_contents($fname2) . "\n";
        } else {
          $s .= '/* File "' . implode('/', $script) . '" not found! */' . "\n";
        };
      };
    }
    return $s;
  }

  /**
  * load_css
  *
  * @param array<mixed> $arr
  * @return string
  */
  public function load_css(array $arr): string {
    return $this->load('css', $arr);
  }

  /**
  * load_js
  *
  * @param array<mixed> $arr
  * @return string
  */
  public function load_js(array $arr): string {
    return $this->load('js', $arr);
  }

  /**
  * get_js
  *
  * @return array<int|string>
  */
  public function get_js(): array {
    return array_keys($this->units['js']);
  }

  /**
  * get_css
  *
  * @return array<int|string>
  */
  public function get_css(): array {
    return array_keys($this->units['css']);
  }

  /**
  * set cache
  *
  * @param mixed $o
  * @param int $ttl
  * @return void
  */
  public function setCache($o, int $ttl = 60): void {
    $this->cache = $o;
    $this->cache_ttl = $ttl;
  }



  function getRelativePath(string $from, string $to): string {
    // some compatibility fixes for Windows paths
    $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
    $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
    $from = str_replace('\\', '/', $from);
    $to   = str_replace('\\', '/', $to);

    $from     = explode('/', $from);
    $to       = explode('/', $to);
    $relPath  = $to;

    foreach($from as $depth => $dir) {
        // find first non-matching dir
        if($dir === $to[$depth]) {
            // ignore this directory
            array_shift($relPath);
        } else {
            // get number of remaining dirs to $from
            $remaining = count($from) - $depth;
            if($remaining > 1) {
                // add traversals up to first matching dir
                $padLength = (count($relPath) + $remaining - 1) * -1;
                $relPath = array_pad($relPath, $padLength, '..');
                break;
            } else {
                $relPath[0] = './' . $relPath[0];
            }
        }
    }
    return implode('/', $relPath);
  }

}
