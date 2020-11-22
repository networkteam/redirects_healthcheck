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

    /**
     * @var string
     */
    protected $targetUrl;

    public function __construct(array $redirect, bool $isHealthy, string $resultText, $targetUrl = '')
    {
        $this->redirect = $redirect;
        $this->isHealthy = $isHealthy;
        $this->resultText = $resultText;
        $this->targetUrl = $targetUrl;
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

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }
}