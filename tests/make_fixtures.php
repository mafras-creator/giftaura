<?php
/**
 * Creates the image files used by admin_e2e.sh (test only, needs GD):
 *   good.jpg  800x600 JPEG        good.png 300x300 PNG
 *   big.jpg   2.5 MB of junk      fake.jpg / shell.php  PHP code, not an image
 *   poly.jpg  valid JPEG with PHP code appended (polyglot)
 *   wide.jpg  1920x600 JPEG (homepage banner)
 *   tiny.jpg  is deliberately NOT created (upload of a missing file)
 */
$dir = __DIR__;
$img = imagecreatetruecolor(800, 600);
imagefill($img, 0, 0, imagecolorallocate($img, 200, 120, 160));
imagejpeg($img, "$dir/good.jpg", 80);
$png = imagecreatetruecolor(300, 300);
imagefill($png, 0, 0, imagecolorallocate($png, 90, 60, 120));
imagepng($png, "$dir/good.png");
$wide = imagecreatetruecolor(1920, 600);
imagefill($wide, 0, 0, imagecolorallocate($wide, 74, 29, 110));
imagejpeg($wide, "$dir/wide.jpg", 80);
file_put_contents("$dir/big.jpg", str_repeat('x', 2500000));
file_put_contents("$dir/fake.jpg", '<?php echo "hacked"; ?>');
file_put_contents("$dir/shell.php", '<?php echo "hacked"; ?>');
file_put_contents("$dir/poly.jpg", file_get_contents("$dir/good.jpg") . '<?php system($_GET["c"]); ?>');
echo "fixtures created\n";
