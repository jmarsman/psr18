<?php
namespace Undercloud\Psr18\Streams;

use ArrayIterator;
use RuntimeException;
use Psr\Http\Message\StreamInterface;
use Undercloud\Psr18\HttpClient;

/**
 * Class MultipartStream
 *
 * @category Psr18HttpClient
 * @package  Undercloud\Psr18
 * @author   undercloud <lodashes@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     http://github.com/undercloud/psr18
 */
class MultipartStream implements StreamInterface
{
    /**
     * @var string
     */
    private $boundary;

    /**
     * @var ArrayIterator
     */
    private $arrayIterator;

    /**
     * @var StreamInterface
     */
    private $streamCursor;

    /**
     * @var int
     */
    private $streamIndex;

    /**
     * @var array
     */
    private $patterns = [
        'plain' => (
            'Content-Disposition: form-data; name="%s"' .
            HttpClient::CRLF . HttpClient::CRLF .
            '%s'
        ),
        'file' => (
            'Content-Disposition: form-data; name="%s"; filename="%s"' . HttpClient::CRLF .
            'Content-Type: %s' . HttpClient::CRLF . HttpClient::CRLF
        )
    ];

    /**
     * MultipartStream constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->arrayIterator = new ArrayIterator();

        $data = $this->arrayToPlain($data);
        if ($data) {
            foreach ($data as $key => $value) {
                $isFile = $value instanceof FileStream;
                $pattern = $this->patterns[$isFile ? 'file' : 'plain'];

                $this->arrayIterator->append(
                    new TextStream(
                        '--' . $this->getBoundary() . HttpClient::CRLF .
                        (
                            $isFile
                                ? sprintf($pattern, $key, $value->getClientFilename(), $value->getClientMediaType())
                                : sprintf($pattern, $key, !($value instanceof StreamInterface) ? $value : '')
                        )
                    )
                );

                if ($value instanceof StreamInterface) {
                    $this->arrayIterator->append($value);
                }

                $this->arrayIterator->append(new TextStream(HttpClient::CRLF));
            }

            $this->arrayIterator->append(
                new TextStream(
                    '--' . $this->getBoundary() . '--'
                )
            );

            $this->streamIndex = 0;
            $this->streamCursor = $this->arrayIterator->offsetGet(0);
        }
    }

    /**
     * Convert array to plain key -> value pairs
     *
     * @param array  $data   array
     * @param string $prefix parent prefix
     *
     * @return array
     */
    private function arrayToPlain(array $data, $prefix = '')
    {
        $result = array();
        foreach ($data as $key => $value) {
            if ($prefix) {
                $index = $prefix . '[' . $key . ']';
            } else {
                $index = $key;
            }

            if (is_array($value)) {
                $result = $result + $this->arrayToPlain($value, $index);
            } else {
                $result[$index] = $value;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents()
    {
        $data = '';
        foreach ($this->arrayIterator as $stream) {
            $data .= $stream->getContents();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        $data = $this->streamCursor->read($length);
        $bytes = strlen($data);
        if ($bytes < $length) {
            if (!$this->eof()) {
                $this->streamCursor = $this->arrayIterator->offsetGet(++$this->streamIndex);
                $data .= $this->read($length - $bytes);
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return (
            $this->streamCursor
            and $this->streamCursor->eof()
            and $this->arrayIterator->count() === $this->streamIndex + 1
        );
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        foreach ($this->arrayIterator as $stream) {
            $stream->detach();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        foreach ($this->arrayIterator as $stream) {
            $stream->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        throw new RuntimeException();
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function write($string)
    {
        throw new RuntimeException();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        foreach ($this->arrayIterator as $stream) {
            $stream->rewind();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new RuntimeException(
            'Stream does not support seeking'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        $size = 0;
        foreach ($this->arrayIterator as $stream) {
            $size += $stream->getSize();
        }

        return $size;
    }

    /**
     * {@inheritdoc}
     */
    public function getBoundary()
    {
        if (null === $this->boundary) {
            $alph = implode(array_merge(
                range('A', 'Z'),
                range('a', 'z'),
                range(0, 9)
            ));

            $this->boundary = substr(str_shuffle($alph), -12);
        }

        return $this->boundary;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getContents();
    }
}