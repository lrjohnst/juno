<?php

function juno_eval(string $broncode): string
{
    $lexer = new JunoLexer($broncode);
    $tokens = $lexer->tokenize();
    $parser = new JunoParser($tokens);
    $program = $parser->parseProgram();
    $runtime = new JunoRuntime();
    $value = $runtime->evalProgram($program);
    return JunoRuntime::formatValue($value);
}

class JunoLexer
{
    private string $src;
    private int $pos = 0;
    private int $len;

    public function __construct(string $src)
    {
        $this->src = $src;
        $this->len = strlen($src);
    }

    public function tokenize(): array
    {
        $tokens = [];
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];

            if (ctype_space($ch)) {
                $this->pos++;
                continue;
            }

            if (ctype_digit($ch)) {
                $tokens[] = $this->readNumber();
                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $tokens[] = $this->readIdentifier();
                continue;
            }

            if ($ch === '=' && $this->peekChar(1) === '=') {
                $tokens[] = ['type' => 'op', 'value' => '=='];
                $this->pos += 2;
                continue;
            }

            if (strpos('+-*/()=,;<>', $ch) !== false) {
                $tokens[] = ['type' => 'op', 'value' => $ch];
                $this->pos++;
                continue;
            }

            $this->pos++;
        }

        $tokens[] = ['type' => 'eof', 'value' => ''];
        return $tokens;
    }

    private function readNumber(): array
    {
        $start = $this->pos;
        while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
            $this->pos++;
        }
        if ($this->pos < $this->len && $this->src[$this->pos] === '.') {
            $this->pos++;
            while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
                $this->pos++;
            }
        }
        return ['type' => 'number', 'value' => substr($this->src, $start, $this->pos - $start)];
    }

    private function readIdentifier(): array
    {
        $start = $this->pos;
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if (!(ctype_alnum($ch) || $ch === '_')) {
                break;
            }
            $this->pos++;
        }
        $word = substr($this->src, $start, $this->pos - $start);
        if ($word === 'fn') {
            return ['type' => 'fn', 'value' => $word];
        }
        return ['type' => 'identifier', 'value' => $word];
    }

    private function peekChar(int $offset): string
    {
        $idx = $this->pos + $offset;
        return $idx < $this->len ? $this->src[$idx] : '';
    }
}

class JunoParser
{
    private array $tokens;
    private int $pos = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function parseProgram(): array
    {
        $statements = [];
        while (!$this->is('eof')) {
            if ($this->matchOp(';')) {
                continue;
            }
            $statements[] = $this->parseStatement();
            $this->matchOp(';');
        }
        return $statements;
    }

    private function parseStatement(): array
    {
        if ($this->matchType('fn')) {
            $name = $this->consume('identifier')['value'];
            $this->consumeOp('(');
            $params = [];
            if (!$this->checkOp(')')) {
                do {
                    $params[] = $this->consume('identifier')['value'];
                } while ($this->matchOp(','));
            }
            $this->consumeOp(')');
            $this->consumeOp('=');
            $body = $this->parseExpression();
            return ['type' => 'fn', 'name' => $name, 'params' => $params, 'body' => $body];
        }

        if ($this->is('identifier') && $this->lookaheadValue(1) === '=') {
            $name = $this->advance()['value'];
            $this->consumeOp('=');
            return ['type' => 'assign', 'name' => $name, 'expr' => $this->parseExpression()];
        }

        return ['type' => 'expr', 'expr' => $this->parseExpression()];
    }

    private function parseExpression(): array
    {
        return $this->parseComparison();
    }

    private function parseComparison(): array
    {
        $node = $this->parseAdditive();
        while ($this->checkOp('==') || $this->checkOp('>') || $this->checkOp('<')) {
            $op = $this->advance()['value'];
            $right = $this->parseAdditive();
            $node = ['type' => 'binary', 'op' => $op, 'left' => $node, 'right' => $right];
        }
        return $node;
    }

    private function parseAdditive(): array
    {
        $node = $this->parseMultiplicative();
        while ($this->checkOp('+') || $this->checkOp('-')) {
            $op = $this->advance()['value'];
            $right = $this->parseMultiplicative();
            $node = ['type' => 'binary', 'op' => $op, 'left' => $node, 'right' => $right];
        }
        return $node;
    }

    private function parseMultiplicative(): array
    {
        $node = $this->parseUnary();
        while ($this->checkOp('*') || $this->checkOp('/')) {
            $op = $this->advance()['value'];
            $right = $this->parseUnary();
            $node = ['type' => 'binary', 'op' => $op, 'left' => $node, 'right' => $right];
        }
        return $node;
    }

    private function parseUnary(): array
    {
        if ($this->matchOp('-')) {
            return ['type' => 'unary', 'op' => '-', 'expr' => $this->parseUnary()];
        }
        return $this->parsePrimary();
    }

    private function parsePrimary(): array
    {
        if ($this->is('number')) {
            return ['type' => 'number', 'value' => $this->advance()['value']];
        }

        if ($this->is('identifier')) {
            $name = $this->advance()['value'];
            if ($this->matchOp('(')) {
                $args = [];
                if (!$this->checkOp(')')) {
                    do {
                        $args[] = $this->parseExpression();
                    } while ($this->matchOp(','));
                }
                $this->consumeOp(')');
                return ['type' => 'call', 'name' => $name, 'args' => $args];
            }
            return ['type' => 'var', 'name' => $name];
        }

        if ($this->matchOp('(')) {
            $expr = $this->parseExpression();
            $this->consumeOp(')');
            return $expr;
        }

        return ['type' => 'number', 'value' => '0'];
    }

    private function is(string $type): bool
    {
        return $this->tokens[$this->pos]['type'] === $type;
    }

    private function checkOp(string $value): bool
    {
        return $this->tokens[$this->pos]['type'] === 'op' && $this->tokens[$this->pos]['value'] === $value;
    }

    private function matchOp(string $value): bool
    {
        if ($this->checkOp($value)) {
            $this->pos++;
            return true;
        }
        return false;
    }

    private function matchType(string $type): bool
    {
        if ($this->is($type)) {
            $this->pos++;
            return true;
        }
        return false;
    }

    private function consume(string $type): array
    {
        if ($this->is($type)) {
            return $this->advance();
        }
        return ['type' => $type, 'value' => ''];
    }

    private function consumeOp(string $value): array
    {
        if ($this->checkOp($value)) {
            return $this->advance();
        }
        return ['type' => 'op', 'value' => $value];
    }

    private function advance(): array
    {
        return $this->tokens[$this->pos++];
    }

    private function lookaheadValue(int $offset): string
    {
        $idx = $this->pos + $offset;
        return $this->tokens[$idx]['value'] ?? '';
    }
}

class JunoRuntime
{
    private array $globals = [];
    private array $functions = [];

    public function evalProgram(array $program)
    {
        $result = 0;
        foreach ($program as $stmt) {
            if ($stmt['type'] === 'fn') {
                $this->functions[$stmt['name']] = ['params' => $stmt['params'], 'body' => $stmt['body']];
                $result = 0;
            } elseif ($stmt['type'] === 'assign') {
                $result = $this->evalExpr($stmt['expr'], []);
                $this->globals[$stmt['name']] = $result;
            } else {
                $result = $this->evalExpr($stmt['expr'], []);
            }
        }
        return $result;
    }

    private function evalExpr(array $node, array $locals)
    {
        switch ($node['type']) {
            case 'number':
                return strpos($node['value'], '.') !== false ? (float)$node['value'] : (int)$node['value'];

            case 'var':
                if (array_key_exists($node['name'], $locals)) {
                    return $locals[$node['name']];
                }
                return $this->globals[$node['name']] ?? 0;

            case 'unary':
                return -$this->evalExpr($node['expr'], $locals);

            case 'binary':
                $left = $this->evalExpr($node['left'], $locals);
                $right = $this->evalExpr($node['right'], $locals);
                switch ($node['op']) {
                    case '+': return $left + $right;
                    case '-': return $left - $right;
                    case '*': return $left * $right;
                    case '/': return $left / $right;
                    case '==': return $left == $right;
                    case '>': return $left > $right;
                    case '<': return $left < $right;
                }
                return 0;

            case 'call':
                if (!isset($this->functions[$node['name']])) {
                    return 0;
                }
                $fn = $this->functions[$node['name']];
                $callLocals = [];
                foreach ($fn['params'] as $i => $param) {
                    $callLocals[$param] = isset($node['args'][$i]) ? $this->evalExpr($node['args'][$i], $locals) : 0;
                }
                return $this->evalExpr($fn['body'], $callLocals);
        }

        return 0;
    }

    public static function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_float($value)) {
            if (abs($value - round($value)) < 0.0000000001) {
                return (string)(int)round($value);
            }
            $text = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
            return $text === '-0' ? '0' : $text;
        }
        return (string)$value;
    }
}
