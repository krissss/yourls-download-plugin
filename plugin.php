<?php
/*
Plugin Name: Download Plugin
Plugin URI: http://your-own-domain-here.com/articles/hey-test-my-sample-plugin/
Description: Download Plugin From Url
Version: 1.0
Author: Kriss
Author URI: http://ozh.org/
*/

if (!defined('YOURLS_ABSPATH')) die();

if (!class_exists('ZipArchive')) {
    yourls_add_notice("<b>Download Plugin</b> plugin need <b>zip</b> extension!");
}

yourls_add_action('plugins_loaded', 'kriss_download_plugin_settings');
function kriss_download_plugin_settings()
{
    yourls_register_plugin_page('download_plugin_settings', 'Download Plugin', 'kriss_download_plugin_page');
}

function kriss_download_plugin_page()
{
    $msg = '';

    if (isset($_POST['github_url'])) {
        yourls_verify_nonce('download_plugin_settings');
        list($is, $txt) = kriss_download_plugin();
        $info = $is ? ['txt' => 'success', 'color' => 'green'] : ['text' => 'fail', 'color' => 'red'];
        $msg = "<p style='color: {$info['color']}'>download {$info['txt']}: {$txt}</p>";
    }

    $nonce = yourls_create_nonce('download_plugin_settings');
    echo <<<HTML
        <main>
            <h2>Download Plugin</h2>
            <p><a href="https://github.com/YOURLS/awesome-yourls" target="_blank">plugin list</a></p>
            {$msg}
            <form method="post">
            <input type="hidden" name="nonce" value="$nonce" />
            <p>
                <label>Github Url</label>
                <input type="text" name="github_url" value="" required />
            </p>
            <p>
                <label>Github Branch</label>
                <input type="text" name="github_branch" value="master" required />
            </p>
            <p>
                <label>Name</label>
                <input type="text" name="download_name" value="" />
                <hint>optional, will get from github url</hint>
            </p>
            <p>
                <label>Plugin path</label>
                <input type="text" name="plugin_path" value="" />
                <hint>optional, where plugin.php exist, root path default</hint>
            </p>
            <p>
                <label>Delete zip after unzip</label>
                <input type="radio" name="delete_after_unzip" value="1" checked />Delete
                <input type="radio" name="delete_after_unzip" value="0" />Keep
            </p>
            <p><input type="submit" value="Download" class="button" /></p>
            </form>
        </main>
HTML;
}

function kriss_download_plugin()
{
    $url = $_POST['github_url'];
    $branch = $_POST['github_branch'];
    $name = $_POST['download_name'];

    if (strpos($url, 'https://github.com/') !== 0) {
        return [false, 'only github url support'];
    }

    // parse github url
    $downloadUrl = "$url/archive/refs/heads/{$branch}.zip";
    $downloadName = $name ?: basename($url) . '.zip';
    $filepath = __DIR__ . '/../' . $downloadName;
    $unzipPath = __DIR__ . '/../';

    // download file
    if (file_exists($filepath)) {
        return [false, 'file ' . $filepath . ' existed'];
    }
    $content = file_get_contents($downloadUrl);
    file_put_contents($filepath, $content);

    // unzip
    $zip = new ZipArchive();
    $unzipOk = false;
    if ($zip->open($filepath) === true) {
        $zip->extractTo($unzipPath);
        $zip->close();
        $unzipOk = true;
    }

    // move plugin.php if need
    if (isset($_POST['plugin_path']) && $_POST['plugin_path']) {
        $pluginPath = $unzipPath . basename($url) . '-' . $branch . '/';
        $realPluginPath = $pluginPath . trim($_POST['plugin_path'], '/') . '/';
        var_dump($pluginPath, $realPluginPath);
        copy($realPluginPath . 'plugin.php', $pluginPath . 'plugin.php');
    }

    // delete file
    if (isset($_POST['delete_after_unzip']) && $_POST['delete_after_unzip']) {
        unlink($filepath);
    }

    if (!$unzipOk) {
        return [false, 'unzip failed'];
    }

    return [true, $downloadName];
}
