<?php
/** Groep 3 — maal en deel binden sterker dan plus en min; haakjes winnen. */
return [
    ['naam' => 'maal voor plus',   'in' => '2 + 3 * 4',      'uit' => '14'],
    ['naam' => 'plus voor maal',   'in' => '3 * 4 + 2',      'uit' => '14'],
    ['naam' => 'haakjes',          'in' => '(2 + 3) * 4',    'uit' => '20'],
    ['naam' => 'nest',             'in' => '((1 + 2) * 3)',  'uit' => '9'],
    ['naam' => 'deel voor min',    'in' => '10 - 6 / 2',     'uit' => '7'],
    ['naam' => 'haakjes unair',    'in' => '-(2 + 3)',       'uit' => '-5'],
];
