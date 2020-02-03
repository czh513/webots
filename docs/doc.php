<?php
  function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
  }

  function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 || (substr($haystack, -$length) === $needle);
  }

  $request_uri = $_SERVER['REQUEST_URI'];
  if (substr($request_uri, 0, 5) != "/doc/") { # redirect aliases
    $request_uri = "/doc/".substr($request_uri, 1)."/index"; // we remove the "/" prefix
    header("Location: $request_uri");
    exit();
  }
  if ($request_uri == "/doc/blog/index") {
    header("Location: /doc/blog/Webots-2020-b-release");
    exit();
  }
  # the URL follow this format https://www.cyberbotics.com/doc/book/page?version=tagOrBranch&tab=C#anchor where version, tab and anchor are optional
  $uri = substr($request_uri, 5); // we remove the "/doc/" prefix
  $path = parse_url($uri, PHP_URL_PATH);
  $query_str = parse_url($uri, PHP_URL_QUERY);
  parse_str($query_str, $query);
  $i = strpos($path, '/');
  unset($repository);
  $branch = '';
  $tab = ''; // For backward compatibility <= R2019b revision 1.
  $tabs = array();
  if ($i !== FALSE) {
    $book = substr($path, 0, $i);
    $page = substr($path, $i + 1);
    foreach ($query as $key => $value) {
      if ($key == 'version') {
        $n = strpos($value, ':');
        if ($n === FALSE)
          $branch = $value;
        else {
          $branch = substr($value, $n + 1);
          $repository = substr($value, 0, $n);
        }
      } else if ($key == 'tab') // For backward compatibility <= R2019b revision 1.
        $tab = $value;
      else if (startsWith($key, 'tab-'))
        $tabs[$key] = $value;
    }
  } else {
    # default values:
    $book = $uri;
    $page = 'index';
    # anchor is not sent to the server, so it has to be computed by the JavaScript
  }
  if (!isset($repository))
    $repository = 'cyberbotics';

  $book = htmlspecialchars($book);
  $page = htmlspecialchars($page);
  $branch = htmlspecialchars($branch);
  $repository = htmlspecialchars($repository);

  if ($branch === '') {
    # get HEAD commit SHA, to ensure that when master is updated the latest version is cached by the CDN
    ini_set('user_agent', $repository); # every GitHub request needs a valid user agent header
    $githubHead = @file_get_contents("https://api.github.com/repos/cyberbotics/webots/git/refs/heads/master");
    // failed request / github is down
    if ($githubHead === FALSE)
      $cacheUrl = "https://cdn.jsdelivr.net/gh/$repository/webots@master";  // fall back to dev URL at worst
    else {
      $githubPhp = json_decode($githubHead);
      $sha = $githubPhp->object->sha;
      $cacheUrl = "https://cdn.jsdelivr.net/gh/$repository/webots@$sha";  // Load the current master snapshot from RawGit CDN.
    }
  } else
    $cacheUrl = "https://cdn.jsdelivr.net/gh/$repository/webots@"; // Load master snapshot from dev URL.

  $scripts = "
    <script>
      setup = {
        'book':       '$book',
        'page':       '$page',
        'tab':        '$tab',  // For backward compatibility <= R2019b revision 1.
        'tabs':       " . json_encode($tabs) . ",
        'anchor':     window.location.hash.substring(1),
        'branch':     '$branch',
        'repository': '$repository',
        'tag':        '',  // For backward compatibility < R2018a.
        'url':        'https://raw.githubusercontent.com/$repository/webots/'
      }
      console.log('Setup: ' + JSON.stringify(setup));
    </script>
    <link rel='stylesheet' type='text/css' href='$cacheUrl$branch/docs/css/webots-doc.css'/>
  ";

  $dependencies = file_get_contents("$cacheUrl$branch/docs/dependencies.txt");
  if ($dependencies == FALSE)  // fallback for doc < R2018a.rev2
    $dependencies = file_get_contents("https://www.cyberbotics.com/files/repository/www/wwi/R2018a/dependencies_fallback.txt");
  foreach (explode(PHP_EOL, $dependencies) as $dependency) {
    if (!startsWith($dependency, "#")) {
      if (startsWith($dependency, "https://")) {
        if (endsWith($dependency, ".css"))
          $scripts .= "<link type='text/css' rel='stylesheet' href='$dependency'/>\n";
        if (endsWith($dependency, ".js"))
          $scripts .= "<script src='$dependency'></script>\n";
      } else {
        if (endsWith($dependency, ".css"))
          $scripts .= "<link type='text/css' rel='stylesheet' href='https://www.cyberbotics.com/" . $dependency . "'/>\n";
        if (endsWith($dependency, ".js"))
          $scripts .= "<script src='https://www.cyberbotics.com/" . $dependency . "'></script>\n";
      }
    }
  }

  $scripts .= "
    <script src='$cacheUrl$branch/docs/js/showdown-extensions.js'></script>
    <script src='$cacheUrl$branch/docs/js/viewer.js'></script>
  ";
  include 'header.php';
?>
    <div class="webots-doc" id="webots-doc" style="padding:0;">
      <div id="left" style="top:44px;height:calc(100% - 44px)">
        <div id="navigation">
          <table>
            <tr>
              <td colspan="3"><a id="toc" title="Table of Contents">&equiv;</a></td>
            </tr>
            <tr>
              <td><a id="previous" title="Previous page">&#x25C0;</a></td>
              <td><a id="up" title="Up page">&#x25B2;</a></td>
              <td><a id="next" title="Next page">&#x25B6;</a></td>
            </tr>
          </table>
        </div>
        <div id="menu"></div>
      </div>
      <div id="handle"></div>
      <div id="center" style="top:44px">
        <div id="content">
          <div id="title">
            <h2 id="title-content">Documentation</h2>
          </div>
          <div id="view"></div>
        </div>
      </div>
    </div>
<?php
  include 'footer.php';
?>
