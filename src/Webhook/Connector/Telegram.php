<?php
namespace App\Webhook\Connector;

use App\Entity;
use GuzzleHttp\Exception\TransferException;

/**
 * Telegram web hook connector.
 *
 * @package App\Webhook\Connector
 */
class Telegram extends AbstractConnector
{
    /**
     * @param Entity\Station $station
     * @param Entity\Api\NowPlaying $np
     * @param array $config
     */
    public function dispatch(Entity\Station $station, Entity\Api\NowPlaying $np, array $config): void
    {
        $bot_token = $config['bot_token'] ?? '';
        $chat_id = $config['chat_id'] ?? '';

        if (empty($bot_token) || empty($chat_id)) {
            $this->logger->error('Webhook '.$this->_getName().' is missing necessary configuration. Skipping...');
            return;
        }

        $client = new \GuzzleHttp\Client([
            'http_errors' => false,
            'timeout' => 4.0,
        ]);

        $messages = $this->_replaceVariables([
            'text' => $config['text'],
        ], $np);

        try {
            $api_url = (!empty($config['api'])) ? rtrim($config['api'], '/') : 'https://api.telegram.org';
            $webhook_url = $api_url.'/bot'.$bot_token.'/sendMessage';

            $request_params = [
                'chat_id' => $chat_id,
                'text' => $messages['text'],
                'parse_mode' => $config['parse_mode'] ?? 'Markdown' // Markdown or HTML
            ];

            $response = $client->request('POST', $webhook_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $request_params,
            ]);

            $this->logger->debug(
                sprintf('Webhook %s returned code %d', $this->_getName(), $response->getStatusCode()),
                [
                    'request_url' => $webhook_url,
                    'request_params' => $request_params,
                    'response_body' => $response->getBody()->getContents()
                ]
            );
        } catch(TransferException $e) {
            $this->logger->error(sprintf('Error from webhook %s (%d): %s', $this->_getName(), $e->getCode(), $e->getMessage()));
        }
    }
}
