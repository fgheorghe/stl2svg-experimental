<?php

namespace php3d\stl2svg;

/**
 * Class LinePlaneIntersect. Provides logic for intersecting a line with a plane.
 *
 * NOTE: Requires: bcscale(16); !!!
 *
 */
class LinePlaneIntersect {
    const EPSILON = 1e-6;

    /**
     * Returns the vector coordinates of the point where a line intersects a plane.
     *
     * Based on:
     * http://stackoverflow.com/questions/5666222/3d-line-plane-intersection
     * https://en.wikipedia.org/wiki/M%C3%B6ller%E2%80%93Trumbore_intersection_algorithm
     * http://www.scratchapixel.com/lessons/3d-basic-rendering/ray-tracing-rendering-a-triangle/moller-trumbore-ray-triangle-intersection
     *
     * @param Vector $lineStart
     * @param Vector $lineEnd
     * @param Vector $planePointCoordinate
     * @param Vector $planeNormalCoordinate
     * @return Vector
     * @throws \Exception If the line is parallel to the plane.
     */
    function intersect(Vector $lineStart, Vector $lineEnd, Vector $planePointCoordinate, Vector $planeNormalCoordinate) : Vector
    {
        $uParameter = $lineEnd->sub($lineStart);
        $dotProduct = $planeNormalCoordinate->dot($uParameter);

        if (abs($dotProduct) > self::EPSILON) {
            $w = $lineStart->sub($planePointCoordinate);
            $factor = bcmul(-1, bcdiv($planeNormalCoordinate->dot($w), $dotProduct));
            $uParameter = $uParameter->multiplyScalar($factor);
            return $uParameter->add($lineStart);
        } else {
            throw new \Exception("Line is parallel to plane.");
        }
    }
}