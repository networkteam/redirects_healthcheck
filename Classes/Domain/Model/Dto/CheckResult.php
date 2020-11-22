<?php

namespace Networkteam\RedirectsHealthcheck\Domain\Model\Dto;

class CheckResult
{
    /**
     * @var array
     */
    protected $redirect;

    /**
     * @var bool
     */
    protected $isHealthy;

    /**
     * @var string
     */
    protected $resultText;

    public function __construct(array $redirect, bool $isHealthy, string $resultText)
    {
        $this->redirect = $redirect;
        $this->isHealthy = $isHealthy;
        $this->resultText = $resultText;
    }

    public function getRedirect(): array
    {
        return $this->redirect;
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    public function getResultText(): string
    {
        return $this->resultText;
    }
}