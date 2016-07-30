<?php

$xml = (array) json_decode(json_encode(simplexml_load_string(file_get_contents("test.html"))), TRUE);
$width = round($xml["@attributes"]["width"]);
$height = round($xml["@attributes"]["height"]);
$coordinates = array();

$result = "";
$z = 0;
$topZ = 0;
$objectName = "";
for ($i = 0; $i < count($xml["g"]); $i++) {
    $layerName = $xml["g"][$i]["@attributes"]["id"];
    $z = $xml["g"][$i]["@attributes"]["z"];
    if ($z > $topZ) $topZ = $z;
    // create image
    $image = imagecreatetruecolor($width, $height);

    // allocate colors
    $bg   = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 0, 0, 255);

    // fill the background
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $bg);
    $points = explode(" ", str_replace(",", " ",$xml["g"][$i]["polygon"]["@attributes"]["points"]));
    $prevObjectName = $objectName;
    $objectName = $xml["g"][$i]["polygon"]["@attributes"]["points"];
    if ($prevObjectName != $objectName) $result .= "G1 Z" . round($topZ) . "\n";
    // draw a polygon
    imagefilledpolygon($image, $points,  count($points) / 2, $blue);

    imagepng($image, "img/" . $layerName . ".png", 0);

    for ($k = 0; $k < $height - 1; $k++) {
        for ($l = 0; $l < $width - 1; $l++) {
            $coordinates[$k][$l] = imagecolorat($image, $l, $k) . "\n";
            if ($coordinates[$k][$l] != 0) {
                $result .= "G1 X" . $k . " Y" . $l . " Z" . $z . "\n";
            }
        }
    }
    imagedestroy($image);
}

echo "F 100\n";
echo $result;
//var_dump($xml);
die();




// flush image
//header('Content-type: image/png');
//imagepng($image);
//imagedestroy($image);

$test = array();
for ($i = 0; $i < 250; $i++) {
    for ($j = 0; $j < 250; $j++) {
        $test[$i][$j] = imagecolorat($image, $i, $j) . "\n";
    }
}

die();
$file = file("test.stl_03.gcode");
$file = array_reverse($file);

for ($i = 0; $i < count($file); $i++) {
    echo $file[$i];
}