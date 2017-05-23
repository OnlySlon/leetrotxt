<?php

include "leetrotxt.class.php";

$idx = 0;
$f = array();
if ($handle = opendir('./testfiles'))
{
    while (false !== ($entry = readdir($handle)))
		if ($entry != "." && $entry != "..")
		{
			echo "<a href='?f=".$idx."'>$entry</a><BR>";
			$f[$idx]  = $entry;
			$idx ++;
		}
}
$path = "./testfiles/TEST.TXT";

if (isset($_GET['f']))
	$path = "./testfiles/".$f[(int) $_GET['f']];

echo "<PRE>";
$int = new LeetroTXT();
$int->imageSet("./test.png", 1920, 1070);
$int->LoadTXT($path);
$int->Draw();


?>

<img src="./test.png">
<?php
//	 echo $int->Log2text();

?>
