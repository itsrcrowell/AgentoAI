<?php
namespace Genaker\MagentoMcpAi\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ConversationActions extends Column
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['conversation_id'])) {
                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                'magentomcpai/conversation/view',
                                ['id' => $item['conversation_id']]
                            ),
                            'label' => __('View'),
                            'hidden' => false,
                        ],
                        'send_transcript' => [
                            'href' => $this->urlBuilder->getUrl(
                                'magentomcpai/conversation/send',
                                ['id' => $item['conversation_id']]
                            ),
                            'label' => __('Send Transcript'),
                            'hidden' => (bool)$item['transcript_sent'],
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                'magentomcpai/conversation/delete',
                                ['id' => $item['conversation_id']]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete Conversation'),
                                'message' => __('Are you sure you want to delete this conversation?')
                            ],
                            'hidden' => false,
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }
}
