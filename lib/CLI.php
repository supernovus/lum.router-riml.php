<?php

namespace Lum\Router;

/**
 * A library for building command line scripts for compiling routes from RIML.
 *
 * See `./bin/routes.php` for an implementation.
 *
 */
class CLI extends \Lum\CLI\ParamsApp
{
  /**
   * The top level config file for routes.
   * Must be in RIML format, may include multiple files using RIML statements.
   */
  public $default_config = 'src/routes/index.yaml';

  /**
   * Each level of nested paths should be incremented this much when showing
   * the routes in a friendly format.
   */
  public $increment_indent = 2;

  /**
   * Properties should be indented this much from the path.
   * This is used with increment_indent when displaying routes.
   */
  public $indent_props = 1;

  /**
   * A list of route properties to show in the friendly output.
   * Can be modified on the command line with the -p and -R options.
   */
  public $route_props =
  [
    'name', 'controller', 'method', 'http',
  ];

  /**
   * A template to format 'virtual' routes. Use {path} in the template
   * as the variable which will be replaced with the actual path.
   */
  public $virtual_template = '«{path}»';

  /**
   * If a route has no 'path' defined, use this instead.
   */
  public $no_path = '---';

  /**
   * If a route has an empty 'path' defined, use this instead.
   */
  public $empty_path = '-';

  public function showRoutes (array $routes, int $indent=0)
  {
    $out = '';
    $path_pad = str_repeat(' ', $indent);
    $prop_pad = str_repeat(' ', $indent+$this->indent_props);

    foreach ($routes as $route)
    {
      if (isset($route->path))
      {
        $path = trim($route->path);
        if ($path == '')
        {
          $path = $this->empty_path;
        }
      }
      else
      {
        $path = $this->no_path;
      }

      if (isset($route->virtual) && $route->virtual)
      { // A virtual route is used as a base for child routes.
        $path = str_replace('{path}', $path, $this->virtual_template);
      }

      $out .= "{$path_pad}$path\n";

      foreach ($this->route_props as $rprop)
      {
        if (isset($route->$rprop))
        {
          $out .= $prop_pad.$rprop.': '.json_encode($route->$rprop)."\n";
        }
      }

      if ($route->hasRoutes())
      {
        $inc = $indent + $this->increment_indent;
        $out .= $this->showRoutes($route->getRoutes(), $inc);
      }
    }

    return $out;
  }

  protected function initialize_params ($params, $opts)
  {
    $cf = $this->default_config;
    $params->auto_chain = true;
    $params
      ->useHelp()
      ->group('options', ['visible'=>true, 'label'=>"Options:\n"])
      ->value('i', 'filename', "Input file (default: $cf)", 'configfile')
      ->toggle('c', 'Compile to JSON', 'compile')
      ->value('o', 'filename', 'Output file when compiling (default STDOUT)', 'outfile')
      ->value('p', 'property', 'Property to show (specify multiple times)', 'props')
      ->toggle('R', 'Replace default properties (only used with -p)', 'replace')
      ->group('options2', ['visible'=>true, 'label'=>""])
      ->toggle('pretty', 'Use formatted JSON when compiling', 'pretty')
      ->toggle('show', 'When using -c and -o, show summary as well', 'show');
  }

  protected function handle_default ($opts, $params)
  {
    $conf = isset($opts['configfile'])
      ? $opts['configfile']
      : $this->default_config;

    $riml = new \Riml\Parser($conf);

    if ($opts['compile'])
    { // Compile the RIML to a Lum\Router configuration file.
      $compiler = new \Lum\Router\FromRIML();
      $compiled = $compiler->compile($riml);
      $jo = $opts['pretty'] ? JSON_PRETTY_PRINT : 0;
      $json = json_encode($compiled, $jo);

      if (isset($opts['outfile']))
      { // Output the JSON to a file.
        file_put_contents($opts['outfile'], $json);
        if (!$opts['show'])
        { // We're not showing the summary, time to leave.
          return;
        }
      }
      else
      { // No output file, return the JSON directly.
        return $json."\n";
      }
    }

    // If we're here, let's display the friendly summary.
    if (isset($opts['props']))
    {
      $props = $opts['props'];
      if (!is_array($props)) $props = [$props]; // Must be an array.
      if ($opts['replace'])
      { // We're replacing the default properties.
        $this->route_props = $props;
      }
      else
      { // We're adding to the default properties.
        foreach ($props as $prop)
        {
          if (!in_array($prop, $this->route_props))
          {
            $this->route_props[] = $prop;
          }
        }
      }
    }

    return $this->showRoutes($riml->getRoutes());
  }
}