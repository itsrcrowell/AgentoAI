<?php
namespace Genaker\MagentoMcpAi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Model implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'gpt-3.5-turbo', 'label' => __('GPT-3.5 Turbo')],
            ['value' => 'gpt-4', 'label' => __('GPT-4')],
            ['value' => 'gpt-4-turbo', 'label' => __('GPT-4 Turbo')],
            ['value' => 'gpt-4-32k', 'label' => __('GPT-4 32k')]
        ];
    }
} 