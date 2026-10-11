<?php
/** Groep 5 — vergelijkingen leveren true of false als string. */
return [
    ['naam' => 'gelijk',         'in' => '2 == 2',        'uit' => 'true'],
    ['naam' => 'ongelijk',       'in' => '2 == 3',        'uit' => 'false'],
    ['naam' => 'groter',         'in' => '5 > 3',         'uit' => 'true'],
    ['naam' => 'kleiner',        'in' => '5 < 3',         'uit' => 'false'],
    ['naam' => 'na rekenen',     'in' => '1 + 1 == 2',    'uit' => 'true'],
    ['naam' => 'met variabele',  'in' => 'x = 4; x > 2',  'uit' => 'true'],
];
