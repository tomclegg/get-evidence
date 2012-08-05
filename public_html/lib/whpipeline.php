<?php ;

// Copyright: see COPYING
// Authors: see git-blame(1)

function run_whpipeline($locator, $shasum, $quick=false)
{
    // submit the dataset for processing via whpipeline
    $in_dir = $GLOBALS['gBackendBaseDir'].'/upload/'.$shasum;
    $out_dir = $in_dir . '-out';
    @mkdir($in_dir);
    @mkdir($out_dir);
    if (!is_link($in_dir.'/input.locator')) {
        symlink($locator, $in_dir.'/input.locator');
    }
    $status_json = $out_dir.'/whpipeline-status.json';

    $cmd = 'whpipeline < ../pipeline-get-evidence.json ';
    $cmd .= ' '.escapeshellarg('0/INPUT='.$locator);
    $cmd .= ' '.escapeshellarg('2/GETEV_JSON='.trim(`cat getev-latest.locator`).'/getev-latest.json.gz');
    $cmd .= ' '.escapeshellarg('GET_VERSION='.trim(`git show -s --pretty=format:%H`));
    $cmd .= ' --lockfile ' . escapeshellarg("$out_dir/whpipeline.lock");
    $cmd .= ' --callback-url ' . escapeshellarg("$out_dir/whpipeline-status.json");
    $cmd .= ' --detach';
    $cmd .= ' 2>&1 >'.escapeshellarg("$out_dir/whpipeline.stdout");
    shell_exec('echo '.escapeshellarg($cmd).' | at now');
}
