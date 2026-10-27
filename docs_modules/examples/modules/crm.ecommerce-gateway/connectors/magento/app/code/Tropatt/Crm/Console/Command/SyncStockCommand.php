<?php

namespace Tropatt\Crm\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tropatt\Crm\Model\StockSync;

/**
 * `bin/magento tropatt:sync-stock <file.json> [source_code]`
 */
class SyncStockCommand extends Command
{
    /** @var StockSync */
    private $stockSync;

    /** @var File */
    private $file;

    public function __construct(StockSync $stockSync, File $file, ?string $name = null)
    {
        $this->stockSync = $stockSync;
        $this->file = $file;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('tropatt:sync-stock')
            ->setDescription('Push a JSON stock file to Magento (MSI source items)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to a JSON stock file')
            ->addArgument('source_code', InputArgument::OPTIONAL, 'Inventory source code', 'default');
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = (string)$input->getArgument('file');
        $sourceCode = (string)$input->getArgument('source_code');

        if (!is_file($path)) {
            $output->writeln('<error>File not found: ' . $path . '</error>');

            return 1;
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            $output->writeln('<error>The stock file is not valid JSON</error>');

            return 1;
        }

        $rows = $data['products'] ?? ($data['items'] ?? $data);
        $items = StockSync::mapRows((array)$rows, $sourceCode);

        if ($items === []) {
            $output->writeln('<error>No rows with a sku found</error>');

            return 1;
        }

        $updated = $this->stockSync->applyBatch($items);
        $output->writeln('<info>Updated source items: ' . $updated . '</info>');

        return 0;
    }
}
