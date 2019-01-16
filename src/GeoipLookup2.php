<?php

namespace App;

use GeoIp2\Database\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

class GeoipLookup2 extends Command
{
    /**
     * Configure the app
     */
    protected function configure()
    {
        $this
            ->setName('geoiplookup2')
            ->addArgument('address', InputArgument::REQUIRED, 'IP address or hostname')
            ->addArgument('update', null, 'Update the GeoLite2-Country.mmdb database')
            ->setDescription('geoiplookup2 - look up country using IP Address or hostname')
            ->setHelp('geoiplookup2 [-d directory] [-f filename] <ipaddress|hostname>')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Specify a custom path to a single GeoIP datafile'
            )
            ->addOption(
                'directory',
                'd',
                InputOption::VALUE_REQUIRED,
                'Specify a custom directory containing GeoIP datafile ' .
                '(default /usr/share/GeoIP)'
            )
            ->addOption(
                'country',
                'c',
                InputOption::VALUE_NONE,
                'Return the country name'
            )
            ->addOption(
                'iso',
                'i',
                InputOption::VALUE_NONE,
                'Return country iso code'
            )
        ;
    }

    /**
     * Execute the lookup
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return String result
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lookup = $input->getArgument('address');

        // A simple hack to allow single arguments
        if ($lookup == 'update') {
            return $this->update($input, $output);
        }

        if (filter_var($lookup, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip = $lookup;
        } else {
            $ip = gethostbyname($lookup);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $output->writeln(
                    sprintf('<error>%s is not a valid ip or hostname.</error>', $ip),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                return 1; // exit status
            }
        }

        $file = $this->getDBFile($input, $output);

        if (!$file) {
            return 1;
        }

        $reader = new Reader($file);

        try {
            $record = $reader->country($ip);
        } catch (\Exception $e) {
            $output->writeln(
                sprintf('<error>%s is not a valid GeoLite2 country database.</error>', $ip),
                OutputInterface::VERBOSITY_VERBOSE
            );
            return 1; // exit status
        }

        if ($record->traits->isAnonymous ||
            $record->traits->isAnonymousProxy ||
            $record->traits->isAnonymousVpn ||
            $record->traits->isTorExitNode ||
            $record->traits->isHostingProvider
        ) {
            $cname = 'Anonymous Proxy';
            $ciso = 'A1';
        } else {
            $cname = $record->country->name;
            $ciso = $record->country->isoCode;
        }

        $country = $input->getOption('country');
        $iso = $input->getOption('iso');

        if ($country || $iso) {
            if ($country) {
                $output->writeln($cname);
            }
            if ($iso) {
                $output->writeln($ciso);
            }
        } else {
            $output->writeln('GeoIP Country Edition: ' . $ciso . ', ' . $cname);
        }
        return 0; // exit status
    }

    /**
     * Return the path to GeoLite2-Country.mmdb database
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return String path to GeoLite2-Country.mmdb
     */
    protected function getDBFile(InputInterface $input, OutputInterface $output)
    {
        if ($file = $input->getOption('file')) {
            if (!is_file($file)) {
                $output->writeln(
                    sprintf('<error>%s does not exist.</error>', $file),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                return false;
            } else {
                $output->writeln(
                    sprintf('<info>Using database %s.</info>', $file),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                return $file;
            }
        }

        $directory = $input->getOption('directory') ?: '/usr/share/GeoIP';

        if (is_file($directory . '/GeoLite2-Country.mmdb')) {
            $output->writeln(
                sprintf('<info>Using database %s.</info>', $directory . '/GeoLite2-Country.mmdb'),
                OutputInterface::VERBOSITY_VERBOSE
            );
            return $directory . '/GeoLite2-Country.mmdb';
        } else {
            $output->writeln(
                sprintf('<error>%s does not exist.</error>', $directory . '/GeoLite2-Country.mmdb'),
                OutputInterface::VERBOSITY_VERBOSE
            );
            return false;
        }
    }

    /**
     * Update the GeoLite2-Country database
     *
     * @param  InputInterface  $input  [description]
     * @param  OutputInterface $output [description]
     * @return ExitStatus
     */
    public function update(InputInterface $input, OutputInterface $output)
    {
        $src_url = 'https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz';

        $output->writeln(
            sprintf('<info>Downloading %s.</info>', $src_url),
            OutputInterface::VERBOSITY_VERBOSE
        );

        $src = file_get_contents($src_url);

        $output->writeln(
            '<info>Writing to /tmp/GeoLite2-Country.tar.gz</info>',
            OutputInterface::VERBOSITY_VERBOSE
        );

        file_put_contents('/tmp/GeoLite2-Country.tar.gz', $src);

        $output->writeln(
            '<info>Extracting /tmp/GeoLite2-Country.tar.gz</info>',
            OutputInterface::VERBOSITY_VERBOSE
        );

        passthru('tar xf /tmp/GeoLite2-Country.tar.gz -C /tmp/ --strip=1');

        $directory = $input->getOption('directory') ?: '/usr/share/GeoIP';

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($directory)) {
            try {
                $fileSystem->mkdir($directory, 0775);
            } catch (IOExceptionInterface $exception) {
                $id = trim(`id -u`);
                if (trim(`id -u`) !== 0) {
                    $output->writeln(
                        sprintf('Need root to create %s', $directory)
                    );
                    try {
                        passthru("sudo mkdir " . escapeshellarg($directory));
                    } catch (IOExceptionInterface $exception) {
                        $output->writeln(
                            sprintf('<error>Cannot create %s</error>', $directory)
                        );
                        return 1; // exit status
                    }
                } else {
                    $output->writeln(
                        sprintf('<error>Cannot create %s</error>', $directory)
                    );
                    return 1; // exit status
                }
            }
        }

        $output->writeln(
            sprintf('<info>Copying database to %s</info>', $directory),
            OutputInterface::VERBOSITY_VERBOSE
        );

        try {
            $fileSystem->copy('/tmp/GeoLite2-Country.mmdb', "$directory/GeoLite2-Country.mmdb", true);
        } catch (IOExceptionInterface $exception) {
            if (trim(`id -u`) !== 0) {
                $output->writeln(
                    sprintf('Need root to copy /tmp/GeoLite2-Country.mmdb to %s', $directory)
                );
                try {
                    passthru("sudo cp /tmp/GeoLite2-Country.mmdb $directory/GeoLite2-Country.mmdb");
                } catch (IOExceptionInterface $exception) {
                    $output->writeln(
                        sprintf('<error>Cannot copy /tmp/GeoLite2-Country.mmdb to %s</error>', $directory)
                    );
                    return 1; // exit status
                }
            } else {
                $output->writeln(
                    sprintf('<error>Cannot copy /tmp/GeoLite2-Country.mmdb to %s</error>', $directory)
                );
                return 1; // exit status
            }
        }

        $output->writeln(
            '<info>Cleaning up downloaded files</info>',
            OutputInterface::VERBOSITY_VERBOSE
        );

        @unlink('/tmp/GeoLite2-Country.tar.gz');
        @unlink('/tmp/GeoLite2-Country.mmdb');
        @unlink('/tmp/LICENSE.txt');
        @unlink('/tmp/COPYRIGHT.txt');

        return 0; // exit status
    }
}
