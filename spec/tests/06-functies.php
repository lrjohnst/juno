<?php
/** Groep 6 — functiedefinitie met fn naam(params) = expressie. */
return [
    ['naam' => 'geen arg',       'in' => 'fn twee() = 2; twee()',               'uit' => '2'],
    ['naam' => 'een arg',        'in' => 'fn dubbel(n) = n * 2; dubbel(4)',     'uit' => '8'],
    ['naam' => 'twee args',      'in' => 'fn som(a, b) = a + b; som(3, 4)',     'uit' => '7'],
    ['naam' => 'genest aanroep', 'in' => 'fn dubbel(n) = n * 2; dubbel(dubbel(2))', 'uit' => '8'],
    ['naam' => 'expressie arg',  'in' => 'fn dubbel(n) = n * 2; dubbel(1 + 2)', 'uit' => '6'],
    ['naam' => 'gebruikt var',   'in' => 'f = 3; fn maal(n) = n * f; maal(2)',  'uit' => '6'],
];
