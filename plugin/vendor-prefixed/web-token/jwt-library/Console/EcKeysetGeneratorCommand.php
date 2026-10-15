<?php

declare(strict_types=1);

namespace Hibi\Vendor\Jose\Component\Console;

use InvalidArgumentException;
use Hibi\Vendor\Jose\Component\Core\JWKSet;
use Hibi\Vendor\Jose\Component\KeyManagement\JWKFactory;
use Hibi\Vendor\Symfony\Component\Console\Attribute\AsCommand;
use Hibi\Vendor\Symfony\Component\Console\Input\InputArgument;
use Hibi\Vendor\Symfony\Component\Console\Input\InputInterface;
use Hibi\Vendor\Symfony\Component\Console\Output\OutputInterface;
use function is_string;

#[AsCommand(name: 'keyset:generate:ec', description: 'Generate an EC key set (JWKSet format)',)]
final class EcKeysetGeneratorCommand extends GeneratorCommand
{
    protected static $defaultName = 'keyset:generate:ec';

    protected static $defaultDescription = 'Generate an EC key set (JWKSet format)';

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('quantity', InputArgument::REQUIRED, 'Quantity of keys in the key set.')
            ->addArgument('curve', InputArgument::REQUIRED, 'Curve of the keys.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quantity = (int) $input->getArgument('quantity');
        if ($quantity < 1) {
            throw new InvalidArgumentException('Invalid quantity');
        }
        $curve = $input->getArgument('curve');
        if (! is_string($curve)) {
            throw new InvalidArgumentException('Invalid curve');
        }

        $keyset = new JWKSet([]);
        for ($i = 0; $i < $quantity; ++$i) {
            $args = $this->getOptions($input);
            $keyset = $keyset->with(JWKFactory::createECKey($curve, $args));
        }
        $this->prepareJsonOutput($input, $output, $keyset);

        return self::SUCCESS;
    }
}
