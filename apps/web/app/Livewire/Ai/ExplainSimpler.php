<?php

declare(strict_types=1);

namespace App\Livewire\Ai;

use App\Services\AI\AiGateway;
use App\Services\AI\AiResult;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Explain this simpler — AI Layer feature 03.
 *
 * WHY THIS FEATURE EXISTS. Most quality preparation material in India is written in dense
 * English. A Telugu-medium graduate loses hours decoding sentences before they can begin
 * learning, and that tax is invisible to anyone who reads English comfortably. This turns
 * it into one tap.
 *
 * THE CHEAPEST FEATURE IN THE PRODUCT, and the reason is worth stating: the cache is keyed
 * by the PASSAGE, not the user. "Explain this paragraph" has the same correct answer for
 * every student who selects it, so one generation serves thousands. The spec measures it
 * at a 92% hit rate and roughly ₹0.008 a call against ₹0.074 for a doubt.
 *
 * It rewrites only the selected text and adds no facts. An explanation that introduces a
 * date or a figure the source did not contain is a hallucination wearing a helpful voice.
 */
class ExplainSimpler extends Component
{
    /** The passage being explained. Locked so a client cannot swap it after selection. */
    #[Locked]
    public string $passage = '';

    #[Locked]
    public ?int $materialId = null;

    public ?AiResult $result = null;

    public bool $simpler = false;

    public function mount(string $passage = '', ?int $materialId = null): void
    {
        $this->passage = $passage;
        $this->materialId = $materialId;
    }

    /**
     * @param  string  $passage  the text the reader selected
     */
    public function explain(AiGateway $gateway, string $passage = ''): void
    {
        if ($passage !== '') {
            $this->passage = $passage;
        }

        if (mb_strlen(trim($this->passage)) < 20) {
            return;
        }

        $this->result = $gateway->ask(
            question: $this->prompt(),
            user: auth()->user(),
            feature: 'explain',
            context: [
                'material_id' => $this->materialId,
                // Hashing the passage is what makes the cache content-keyed. Two students
                // selecting the same paragraph share one generation.
                'passage_hash' => hash('sha256', trim($this->passage)),
            ],
        );
    }

    /**
     * Ask again, simpler still.
     *
     * A second level exists because "simpler" is relative: a student who did not follow the
     * first explanation has learned nothing from being given it again in the same register.
     */
    public function evenSimpler(AiGateway $gateway): void
    {
        $this->simpler = true;
        $this->explain($gateway);
    }

    private function prompt(): string
    {
        $instruction = $this->simpler
            ? 'Explain this again, far more simply, as if to someone who has just started preparing.'
            : 'Explain this passage in plain language.';

        return $instruction
            ."\n\nRewrite ONLY what is below. Do not add any fact, date or figure that is "
            .'not already in it. If it names something that appears in past papers, say so '
            ."in one line at the end.\n\nPassage:\n".$this->passage;
    }

    public function render()
    {
        return view('livewire.ai.explain-simpler');
    }
}
