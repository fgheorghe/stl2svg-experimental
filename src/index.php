<?php

require("../vendor/autoload.php");

bcscale(16);
$stl = \php3d\stl\STL::fromString(file_get_contents("test.stl"));
echo (new \php3d\stl2svg\STL2Svg($stl, 10, new \php3d\stl2svg\LinePlaneIntersect()))->toString();