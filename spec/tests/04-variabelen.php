<?php
/** Groep 4 — toekenning met =, meerdere opdrachten gescheiden door ;.
 *  De uitvoer is de waarde van de laatste expressie. */
return [
    ['naam' => 'toekennen',      'in' => 'x = 5; x',            'uit' => '5'],
    ['naam' => 'gebruiken',      'in' => 'x = 5; x + 3',        'uit' => '8'],
    ['naam' => 'overschrijven',  'in' => 'x = 1; x = 2; x',     'uit' => '2'],
    ['naam' => 'twee namen',     'in' => 'a = 2; b = 3; a * b', 'uit' => '6'],
    ['naam' => 'uit expressie',  'in' => 'a = 1 + 1; a * a',    'uit' => '4'],
    ['naam' => 'lange naam',     'in' => 'totaal = 10; totaal / 2', 'uit' => '5'],
];
