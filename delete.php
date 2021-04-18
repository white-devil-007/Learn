<?php 
    // open this directory 
    $myDirectory = opendir("image/");

    # Table HTML
    echo "<TABLE border=1 cellpadding=5 cellspacing=0 class=whitelinks>\n";
    echo "<TR><TH>Files Deleted</TH></TR>\n";

    $count = $decount = 0;
    // get each entry
    while($entryName = readdir($myDirectory)) {
        $count++;
        $f = explode(".", $entryName);
        if(!isset($f[1])) {
            $decount++;
            echo "<tr><td>".$entryName."</td></tr>";
            unlink("image/".$entryName);
        }
        unset($f);
    }

    // close directory
    closedir($myDirectory);
    echo "</TABLE>\n";
    echo "Number Of Files: ".$count."\n\nNumber of Files Deleted: ".$decount."\n\nNumber Of Files Available = ".($count-$decount);
