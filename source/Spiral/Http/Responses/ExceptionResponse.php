<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Http\Responses;

use Psr\Http\Message\StreamInterface;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Response;
use Spiral\Views\ViewInterface;

/**
 * ClientException related response with ability to render error page on demand.
 *
 * This class is questionable.
 *
 * @todo Drop this class.
 */
class ExceptionResponse extends Response
{
    /**
     * @var ClientException
     */
    private $exception = null;

    /**
     * Error page to be rendered.
     *
     * @var ViewInterface
     */
    private $view = null;

    /**
     * @param ClientException    $exception
     * @param ViewInterface|null $view
     */
    public function __construct(ClientException $exception, ViewInterface $view = null)
    {
        $this->exception = $exception;

        $headers = [];
        if (!empty($this->view = $view)) {
            $headers['Content-Type'] = 'text/html';
        }

        //We will write to memory on demand, response can be freely modified until body is touched
        parent::__construct('php://memory', $exception->getCode(), $headers);
    }

    /**
     * Get related client exception.
     *
     * @return ClientException
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body)
    {
        //We can't use our view anymore
        $this->view = null;

        return parent::withBody($body);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        $body = parent::getBody();

        if (!empty($this->view)) {
            if ($body->isWritable()) {
                //Let's render view content
                $body->write($this->view->render());
            }

            $this->view = null;
        }

        return $body;
    }
}