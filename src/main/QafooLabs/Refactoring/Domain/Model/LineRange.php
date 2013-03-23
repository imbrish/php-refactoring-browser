<?php

namespace QafooLabs\Refactoring\Domain\Model;

/**
 * A range of lines.
 */
class LineRange
{
    private $lines = array();

    static public function fromString($range)
    {
        list($start, $end) = explode("-", $range);

        $list = new self();

        for ($i = $start; $i <= $end; $i++) {
            $list->lines[$i] = $i;
        }

        return $list;
    }

    public function isInRange($line)
    {
        return isset($this->lines[$line]);
    }

    public function getStart()
    {
        return (int)min($this->lines);
    }

    public function getEnd()
    {
        return (int)max($this->lines);
    }

    public function sliceCode($code)
    {
        $selectedCode = explode("\n", $code);
        $numLines = count($selectedCode);

        for ($i = 0; $i < $numLines; $i++) {
            if ( ! $this->isInRange($i+1)) {
                unset($selectedCode[$i]);
            }
        }

        return array_values($selectedCode);
    }
}
