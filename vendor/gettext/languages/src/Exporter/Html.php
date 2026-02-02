<?php

namespace Gettext\Languages\Exporter;

class Html extends Exporter
{
    /**
     * {@inheritdoc}
     *
     * @see \Gettext\Languages\Exporter\Exporter::getDescription()
     */
    public static function getDescription()
    {
        return 'Build a HTML table';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Gettext\Languages\Exporter\Exporter::toStringDoWithOptions()
     */
    protected static function toStringDoWithOptions($languages, array $options)
    {
        $lines = array();
        $lines[] = '<table>';
        $lines[] = '    <thead>';
        $lines[] = '        <tr>';
        $lines[] = '            <th>Language code</th>';
        $lines[] = '            <th>Language name</th>';
        $lines[] = '            <th># plurals</th>';
        $lines[] = '            <th>Formula</th>';
        $lines[] = '            <th>Plurals</th>';
        $lines[] = '        </tr>';
        $lines[] = '    </thead>';
        $lines[] = '    <tbody>';
        foreach ($languages as $lc) {
            $lines[] = '        <tr>';
            $lines[] = '            <td>' . $lc->id . '</td>';
            $name = self::h($lc->name);
            if (isset($lc->supersededBy)) {
                $name .= '<br /><small><span>Superseded by</span> ' . $lc->supersededBy . '</small>';
            }
            $lines[] = '            <td>' . $name . '</td>';
            $lines[] = '            <td>' . count($lc->categories) . '</td>';
            $lines[] = '            <td>' . self::h($lc->formula) . '</td>';
            $cases = array();
            foreach ($lc->categories as $c) {
                $cases[] = '<li><span>' . $c->id . '</span><code>' . self::h($c->examples) . '</code></li>';
            }
            $lines[] = '            <td><ol start="0">' . implode('', $cases) . '</ol></td>';
            $lines[] = '        </tr>';
        }
        $lines[] = '    </tbody>';
        $lines[] = '</table>';

        return implode("\n", $lines);
    }

    protected static function h($str)
    {
        return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
    }
}
