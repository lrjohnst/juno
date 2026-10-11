<?php
declare(strict_types=1);

/**
 * Door de dry-run-stub ingeleverd. Haalt groep 1 tot en met 3.
 */
function juno_eval(string $broncode): string
{
    $parser = new class(trim($broncode)) {
        private int $pos = 0;
        public function __construct(private string $t) {}

        public function evalueer(): float
        {
            return $this->som();
        }

        private function spring(): void
        {
            while ($this->pos < strlen($this->t) && ctype_space($this->t[$this->pos])) { $this->pos++; }
        }

        private function kijk(): string
        {
            $this->spring();
            return $this->pos < strlen($this->t) ? $this->t[$this->pos] : '';
        }

        private function som(): float
        {
            $links = $this->product();
            while (true) {
                $teken = $this->kijk();
                if ($teken === '+') { $this->pos++; $links += $this->product(); }
                elseif ($teken === '-') { $this->pos++; $links -= $this->product(); }
                else { return $links; }
            }
        }

        private function product(): float
        {
            $links = $this->unair();
            while (true) {
                $teken = $this->kijk();
                if ($teken === '*') { $this->pos++; $links *= $this->unair(); }
                elseif ($teken === '/') { $this->pos++; $links /= $this->unair(); }
                else { return $links; }
            }
        }

        private function unair(): float
        {
            if ($this->kijk() === '-') { $this->pos++; return -$this->unair(); }
            return $this->atoom();
        }

        private function atoom(): float
        {
            if ($this->kijk() === '(') {
                $this->pos++;
                $waarde = $this->som();
                if ($this->kijk() === ')') { $this->pos++; }
                return $waarde;
            }
            $start = $this->pos;
            while ($this->pos < strlen($this->t)
                   && (ctype_digit($this->t[$this->pos]) || $this->t[$this->pos] === '.')) {
                $this->pos++;
            }
            if ($start === $this->pos) {
                throw new RuntimeException('geen getal op positie ' . $this->pos);
            }
            return (float) substr($this->t, $start, $this->pos - $start);
        }
    };

    $uitkomst = $parser->evalueer();

    // Hele getallen zonder komma teruggeven, zoals de tests verwachten.
    return $uitkomst == (int) $uitkomst ? (string) (int) $uitkomst : (string) $uitkomst;
}