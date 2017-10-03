<!DOCTYPE html>
<html>
<head>
    <title>æternity deployment</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="kube.min.css"/>
    <style>
        html, body {
            margin: 0.5em;
        }

        input[type="submit"] {
            display: inline-block;
            margin: 0 0.5em 0.5em 0;
        }
    </style>
</head>
<body>

<?php
$config = include_once 'config.php';

/* BASIC AUTH */
$login_successful = false;
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    if ($_SERVER['PHP_AUTH_USER'] == $config['username'] && $_SERVER['PHP_AUTH_PW'] == $config['password']) {
        $login_successful = true;
    }
}
if (!$login_successful) {
    header('WWW-Authenticate: Basic realm="aeternity git"');
    header('HTTP/1.0 401 Unauthorized');
    print "Login failed!\n";
} else {
    include_once 'AnsiToHtmlConverter.php';

    function run_and_print_shell_command($command)
    {
        $converter = new AnsiToHtmlConverter();
        $stdout = shell_exec($command);
        echo '<strong><pre>' . $command . '</pre></strong>';
        echo '<pre style="background-color: #073642; overflow: auto; padding: 10px 15px; font-family: monospace;">';
        echo $converter->convert($stdout);
        echo '</pre>';
    }

    chdir($config['repo_path']);
    shell_exec('git fetch');

    $local_commit_hashes = preg_split('/\s+/', trim(shell_exec('git log --pretty=format:"%H"')));
    $remote_commit_hashes = preg_split('/\s+/', trim(shell_exec('git log origin/master --pretty=format:"%H"')));
    $unmerged_commits_hashes = array_diff($remote_commit_hashes, $local_commit_hashes);

    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'merge') {
            if (isset($_POST['commitHash']) && strlen($_POST['commitHash']) > 0) {
                $commit_hash = trim($_POST['commitHash']);
                if (in_array($commit_hash, $unmerged_commits_hashes)) {
                    echo '<h3>merge</h3>';
                    run_and_print_shell_command('git merge ' . $commit_hash);
                    unset($unmerged_commits_hashes[array_search($commit_hash, $unmerged_commits_hashes)]);
                }
            }
        } else if ($_POST['action'] == 'commit & push') {
            if (isset($_POST['commitMessage']) && strlen($_POST['commitMessage']) > 0) {
                echo '<h3>commit &amp; push</h3>';
                run_and_print_shell_command('git commit -am' . escapeshellarg($_POST['commitMessage']));
                run_and_print_shell_command('git push 2>&1');
                run_and_print_shell_command('git log origin/master --name-status --color -n 5');
            }
        }
    }
    echo '<form method="post">';

    $remote_changed = strlen(shell_exec('git log origin/master --not master --stat --name-status --color -n 5')) > 0;
    $local_changed = strpos(shell_exec('git -c color.status=always status'), 'Changes not staged for commit') !== false;

    echo '<h3>status</h3>';
    run_and_print_shell_command('git -c color.status=always status');

    /* remote changes */
    if ($remote_changed) {
        echo '<h3>remote changes</h3>';
        run_and_print_shell_command('git log origin/master --not master --stat --name-status --color');
        echo "<h4>diff to current version</h4>";
        run_and_print_shell_command('git diff --color master origin/master');

        echo '<label>Umnerged Commits:<br/>';
        echo '<select name="commitHash" id="commitHash"><option value="">-----</option>';
        foreach ($unmerged_commits_hashes as &$commitHash) {
            echo '<option value="' . $commitHash . '">' . $commitHash . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<input class="button outline" type="submit" name="action" value="merge"/>';
        echo '<input class="button secondary outline" type="submit" name="action" value="diff"/>';
    }
    /* diff */
    if (isset($_POST['action']) && $_POST['action'] == 'diff') {
        $commit_hash = trim($_POST['commitHash']);
        if (in_array($commit_hash, $unmerged_commits_hashes)) {
            run_and_print_shell_command('git diff --color ' . $commit_hash . '^ ' . $commit_hash);
        }
    }
    /* local changes */
    if ($local_changed && !$remote_changed) {
        echo '<h3>local changes</h3>';
        run_and_print_shell_command('git diff --color');
        echo '<textarea name="commitMessage" rows="5" cols="80" placeholder="commit message"></textarea><br/>';
        echo '<input class="button outline" type="submit" name="action" value="commit & push"/>';
    }
    echo '</form>';
}
?>

</body>
</html>