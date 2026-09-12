<?php

declare(strict_types=1);

namespace App\Livewire\Ai;

use App\Services\AI\AiGateway;
use App\Services\AI\AiResult;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ask Sadhana — the grounded assistant.
 *
 * The only AI feature that earns its own destination. Everything else sits inside the
 * screen where the need already exists, because putting it all behind an "AI" menu item
 * would make AI the product. It is not: the product is finding a job and preparing for it,
 * and AI is how some of those screens get better.
 *
 * Every state this component can be in is a designed state, including the refusals. A
 * visible "I don't know, ask the community" builds more trust than a fluent wrong answer,
 * and 1,842 people a week asking what the cutoff will be and getting real historical data
 * instead of a guess is the product working, not failing.
 */
class AskSadhana extends Component
{
    public string $question = '';

    /** Scope, e.g. a notification id when asked from that page. */
    #[Locked]
    public array $context = [];

    public ?AiResult $result = null;

    public bool $asked = false;

    /**
     * @param  array<string, mixed>  $context
     */
    public function mount(array $context = []): void
    {
        $this->context = $context;
    }

    public function ask(AiGateway $gateway): void
    {
        $this->validate([
            'question' => ['required', 'string', 'min:8', 'max:500'],
        ], [
            'question.min' => __('Please write a little more so we can search properly.'),
        ]);

        $this->asked = true;

        // Everything — caps, routing, retrieval, guards, metering — happens inside the
        // gateway. This component never decides anything about safety or cost.
        $this->result = $gateway->ask(
            question: $this->question,
            user: auth()->user(),
            feature: 'ask',
            context: $this->context,
        );
    }

    public function reset_(): void
    {
        $this->reset('question', 'result', 'asked');
    }

    public function render()
    {
        return view('livewire.ai.ask-sadhana');
    }
}
