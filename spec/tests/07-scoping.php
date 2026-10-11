<?php
/** Groep 7 — parameters zijn lokaal en overschaduwen de buitenwereld. */
return [
    ['naam' => 'param lokaal',   'in' => 'n = 9; fn f(n) = n + 1; f(1)',        'uit' => '2'],
    ['naam' => 'buiten intact',  'in' => 'n = 9; fn f(n) = n + 1; f(1); n',     'uit' => '9'],
    ['naam' => 'twee params',    'in' => 'a = 1; b = 2; fn f(a, b) = a - b; f(5, 3)', 'uit' => '2'],
];
