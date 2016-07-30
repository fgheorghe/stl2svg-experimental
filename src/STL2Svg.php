<?php

namespace php3d\stl2svg;
use php3d\stl\STL;
use php3d\stl\STLSplit;

/**
 * Class STL2Svg. Converts and STL object to SVG layers.
 * @package php3d\stl2svg
 */
class STL2Svg {
    /**
     * @var STL
     */
    private $stl;

    /**
     * @var LinePlaneIntersect
     */
    private $linePlaneIntersectLibrary;

    /**
     * @return LinePlaneIntersect
     */
    public function getLinePlaneIntersectLibrary(): LinePlaneIntersect
    {
        return $this->linePlaneIntersectLibrary;
    }

    /**
     * @param LinePlaneIntersect $linePlaneIntersectLibrary
     * @return STL2Svg
     */
    public function setLinePlaneIntersectLibrary(LinePlaneIntersect $linePlaneIntersectLibrary): STL2Svg
    {
        $this->linePlaneIntersectLibrary = $linePlaneIntersectLibrary;
        return $this;
    }

    /**
     * @var int
     */
    private $precision;

    /**
     * @return int
     */
    private function getPrecision(): int
    {
        return $this->precision;
    }

    /**
     * @param int $precision
     * @return STL2Svg
     */
    public function setPrecision(int $precision): STL2Svg
    {
        $this->precision = $precision;
        return $this;
    }

    /**
     * @return STL
     */
    public function getStl(): STL
    {
        return $this->stl;
    }

    /**
     * @param STL $stl
     * @return STL2Svg
     */
    private function setStl(STL $stl): STL2Svg
    {
        $this->stl = $stl;
        return $this;
    }

    /**
     * STL2Svg constructor.
     * @param STL $stl
     * @param int $precision
     * @param LinePlaneIntersect $linePlaneIntersectLibrary
     */
    public function __construct(STL $stl, int $precision, LinePlaneIntersect $linePlaneIntersectLibrary)
    {
        $this->setStl($stl);
        $this->setPrecision($precision);
        $this->setLinePlaneIntersectLibrary($linePlaneIntersectLibrary);
    }

    public function toString()
    {
        $objects = (new STLSplit($this->getStl()))->split();

        $objectsSvg = array();
        $objectsLayers = array();
        $layersNames = array();

        foreach ($objects as $object) {
            $lines = $this->extractLinesFromStl($object);
            $lowestZ = $this->findLowestZ($lines);
            $highestZ = $this->findHighestZ($lines);

            $planeNormalCoordinate = new Vector(0, 0, 1);
            $layers = array();
            $layer = 0;
            for ($i = $lowestZ; $i < $highestZ * $this->getPrecision(); $i++) {
                $planePointCoordinate = new Vector(0, 0, $i / $this->getPrecision());
                $layers[$layer] = array(); // Stores dot vectors.
                $intersectingLines = $this->findAllLinesIntersectingWithPlane($lines, $planePointCoordinate, $layers[$layer]);
                foreach ($intersectingLines as $intersectingLine) {
                    $layers[$layer][] = $this->getLinePlaneIntersectLibrary()->intersect(
                        $intersectingLine[0],
                        $intersectingLine[1],
                        $planePointCoordinate,
                        $planeNormalCoordinate
                    );
                }
                if (count($layers[$layer])) {
                    $layer++;
                }
            }
            $objectsLayers[] = $layers;
            $layersNames[] = $object->getSolidName();
        }

        $lowestX = 0;
        $highestX = 0;
        $lowestY = 0;
        $highestY = 0;
        for ($k = 0; $k < count($objectsLayers); $k++) {
            for ($i = 0; $i < count($objectsLayers[$k]); $i++) {
                foreach ($objectsLayers[$k][$i] as $dot) {
                    if ($dot->getX() < $lowestX) {
                        $lowestX = $dot->getX();
                    }
                    if ($dot->getY() < $lowestY) {
                        $lowestY = $dot->getY();
                    }
                    if ($dot->getX() > $highestX) {
                        $highestX = $dot->getX();
                    }
                    if ($dot->getY() > $highestY) {
                        $highestY = $dot->getY();
                    }
                }
            }
        }

        $addX = 0;
        $addY = 0;
        if ($lowestX < 0) $addX = -1 * $lowestX;
        if ($lowestY < 0) $addY = -1 * $lowestY;

        foreach ($objectsLayers as $key => $layers) {
            $objectsSvg[] = $this->createObjectSvg($layers, $addX, $addY, $lowestX, $lowestY, $highestX, $highestY, $layersNames[$key]);
        }

        return $this->createSvg($objectsSvg);
    }

    private function createSvg(array $objectsSvg) : string
    {
        $height = 0;
        $width = 0;
        $result = "";

        foreach ($objectsSvg as $objectSvg) {
            if ($objectSvg["height"] > $height) $height = $objectSvg["height"];
            if ($objectSvg["width"] > $width) $width = $objectSvg["width"];

            $result .= $objectSvg["svg"] . "\n";
        }

        $result .= "</svg>\n";
        $result = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.0//EN\" \"http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd\">
<svg xmlns=\"http://www.w3.org/2000/svg\" height=\"" . $height . "\" width=\"" . $width . "\">\n" . $result;

        return $result;
    }

    // http://stackoverflow.com/questions/29610770/draw-a-polygon-between-coordinates-preventing-intersects
    private function findCenter($coordinates) : array
    {
        $x = 0; $y = 0;
        foreach ($coordinates as $coordinate) {
            $x += $coordinate->getX();
            $y += $coordinate->getY();
        }

        return array(
            "x" => $x / count($coordinates),
            "y" => $y / count($coordinates)
        );
    }

    private function findAngles(array $centre, array $coordinates) : array
    {
        $angles = array();

        foreach ($coordinates as $coordinate) {
            $angle = atan2(
                $coordinate->getX() - $centre["x"],
                $coordinate->getY() - $centre["y"]
            );

            $angles[] = array(
                "x" => $coordinate->getX(),
                "y" => $coordinate->getY(),
                "angle" => $angle
            );
        }

        return $angles;
    }

    private function sortPolygonCoordinates(array $coordinates) {
        $angles = $this->findAngles($this->findCenter($coordinates), $coordinates);

        usort($angles, function($a, $b) {
            if ($a["angle"] > $b["angle"]) return 1;
            else if ($a["angle"] < $b["angle"]) return -1;
            return 0;
        });

        return $angles;
    }

    /**
     * Creates SVG polygon.
     *
     * @param array $layers
     * @param float $addX
     * @param float $addY
     * @param float $lowestX
     * @param float $lowestY
     * @param float $highestX
     * @param float $highestY
     * @return array
     */
    private function createObjectSvg(array $layers, float $addX, float $addY, float $lowestX, float $lowestY, float $highestX, float $highestY, string $name) : array
    {
        $result = "";

        for ($i = count($layers) - 1; $i > 0; $i--) {
            $result .= "<g id=\"layer" . $i . "\" z=\"" . ($layers[$i][0]->getZ()) . "\">\n";
            $layers[$i] = $this->sortPolygonCoordinates($layers[$i]);
            $coordinates = array();
            foreach ($layers[$i] as $dot) {
                $coordinates[] = ($addX + $dot["x"]) . "," . ($addY + $dot["y"]);
            }
            $result .= "<polygon name=\"" . $name . "\" type=\"contour\" points=\"" . implode(" ", $coordinates) . " " . $coordinates[0] . "\" style=\"fill:lime;stroke:purple;stroke-width:1\" />";
            $result .= "</g>\n";
        }

        $width = (($lowestX < 0) ? -1 * $lowestX : $lowestX) + (($highestX < 0) ? -1 * $highestX : $highestX);
        $height = (($lowestY < 0) ? -1 * $lowestY : $lowestY) + (($highestY < 0) ? -1 * $highestY : $highestY);

        return array(
            "height" => $height,
            "width" => $width,
            "svg" => $result
        );
    }

    /**
     * Find all the lines that WILL intersect with a plane.
     *
     * @param array $lines
     * @param Vector $planePointCoordinate
     * @param $layer array
     * @return array
     */
    private function findAllLinesIntersectingWithPlane(array $lines, Vector $planePointCoordinate, array &$layer) : array
    {
        $z = $planePointCoordinate->getZ();
        $intersectingLines = array();
        foreach ($lines as $line) {
            if (($line[0]->getZ() < $z && $line[1]->getZ() > $z)
                || ($line[1]->getZ() < $z && $line[0]->getZ() > $z)){
                $intersectingLines[] = $line;
            }
            if ($line[0]->getZ() == $z) {
                $layer[] = $line[0];
            }
            if ($line[1]->getZ() == $z) {
                $layer[] = $line[1];
            }
        }

        return $intersectingLines;
    }

    /**
     * Find the lowest Z to figure out the lowest layer.
     *
     * @param array $linesArray
     * @return float
     */
    private function findLowestZ(array $linesArray) : float
    {
        $lowestZ = null;
        foreach ($linesArray as $line) {
            if (is_null($lowestZ)) {
                $lowestZ = $line[0]->getZ();
            }
            if ($line[0]->getZ() < $lowestZ) {
                $lowestZ = $line[0]->getZ();
            }
            if ($line[1]->getZ() < $lowestZ) {
                $lowestZ = $line[1]->getZ();
            }
        }
        return $lowestZ;
    }

    /**
     * Fined the highest Z point, to figure out the highest layer.
     *
     * @param array $linesArray
     * @return float
     */
    private function findHighestZ(array $linesArray) : float
    {
        $highestZ = null;
        foreach ($linesArray as $line) {
            if (is_null($highestZ)) {
                $highestZ = $line[0]->getZ();
            }
            if ($line[0]->getZ() > $highestZ) {
                $highestZ = $line[0]->getZ();
            }
            if ($line[1]->getZ() > $highestZ) {
                $highestZ = $line[1]->getZ();
            }
        }
        return $highestZ;
    }

    /**
     * Build a list of lines (segments) from an STL object.
     *
     * @param $stl
     * @return array
     */
    private function extractLinesFromStl(STL $stl) : array
    {
        $stlArray = $stl->toArray();
        $lines = array();

        foreach ($stlArray["facets"] as $facet) {
            $lines[] = array(
                Vector::fromArray($facet["vertex"][0]), Vector::fromArray($facet["vertex"][1])
            );
            $lines[] = array(
                Vector::fromArray($facet["vertex"][1]), Vector::fromArray($facet["vertex"][2])
            );
            $lines[] = array(
                Vector::fromArray($facet["vertex"][2]), Vector::fromArray($facet["vertex"][0])
            );
        }

        return $lines;
    }
}