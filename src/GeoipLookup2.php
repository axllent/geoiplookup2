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
            ->addArgument(
                'lookup',
                InputArgument::REQUIRED,
                'IP address or hostname'
            )
            ->addArgument(
                'self-update',
                null,
                'Update the geoiplookup binary (/usr/local/bin, -d for other directory)'
            )
            ->addArgument(
                'db-update',
                null,
                'Update the GeoLite2-Country.mmdb database (/usr/share/GeoIP, -d for other directory)'
            )
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
                'Specify a custom directory for GeoIP datafile (/usr/share/GeoIP), ' .
                'or installation directory (/usr/local/bin)'
            )
            ->addOption(
                'country',
                'c',
                InputOption::VALUE_NONE,
                'Return the country name (eg: New Zealand)'
            )
            ->addOption(
                'iso',
                'i',
                InputOption::VALUE_NONE,
                'Return country iso code (eg: NZ)'
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
        $lookup = $input->getArgument('lookup');

        // A simple hack to allow single arguments
        if (in_array($lookup, ['self-update', 'selfupdate'])) {
            return $this->doSelfUpdate($input, $output);
        }

        if (in_array($lookup, ['db-update', 'update'])) {
            return $this->doDbUpdate($input, $output);
        }

        // Assume everything else is a lookup
        if (filter_var($lookup, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip = $lookup;
        } else {
            $ip = gethostbyname($lookup);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $output->writeln(
                    sprintf('<error>%s is not a valid ip or hostname.</error>', $ip),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                return 1;
            }
        }

        $file = $this->getDBPath($input, $output);

        if (!$file) {
            $output->writeln(
                '<error>GeoLite2 Country database not found. Please run:</error> geoiplookup update'
            );
            return 1;
        }

        $country = $input->getOption('country');
        $iso = $input->getOption('iso');

        $reader = new Reader($file);

        try {
            $record = $reader->country($ip);
        } catch (\Exception $e) {
            if (!$country && !$iso) {
                $output->writeln('GeoIP Country Edition: IP Address not found');
            }
            return 1;
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

        if ($country || $iso) {
            if ($country) {
                $output->writeln($cname);
            }
            if ($iso) {
                $output->writeln($ciso);
            }
        } else {
            strlen($ciso) == 2 ?
            $output->writeln('GeoIP Country Edition: ' . $ciso . ', ' . $cname) :
            $output->writeln('GeoIP Country Edition: IP Address not found');
        }
        return 0;
    }

    /**
     * Return the path to GeoLite2-Country.mmdb database
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return String path to GeoLite2-Country.mmdb
     */
    protected function getDBPath(InputInterface $input, OutputInterface $output)
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
    public function doDbUpdate(InputInterface $input, OutputInterface $output)
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
                    try {
                        passthru('sudo mkdir ' . escapeshellarg($directory));
                    } catch (IOExceptionInterface $exception) {
                        $output->writeln(
                            sprintf('<error>Cannot create %s</error>', $directory)
                        );
                        return 1;
                    }
                } else {
                    $output->writeln(
                        sprintf('<error>Cannot create %s</error>', $directory)
                    );
                    return 1;
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
                try {
                    passthru("sudo cp /tmp/GeoLite2-Country.mmdb $directory/GeoLite2-Country.mmdb");
                } catch (IOExceptionInterface $exception) {
                    $output->writeln(
                        sprintf('<error>Cannot copy /tmp/GeoLite2-Country.mmdb to %s</error>', $directory)
                    );
                    return 1;
                }
            } else {
                $output->writeln(
                    sprintf('<error>Cannot copy /tmp/GeoLite2-Country.mmdb to %s</error>', $directory)
                );
                return 1;
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

        return 0;
    }

    /**
     * Run self-update
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    public function doSelfUpdate(InputInterface $input, OutputInterface $output)
    {
        $directory = $input->getOption('directory') ?: '/usr/local/bin';

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($directory)) {
            $output->writeln(
                sprintf('<error>Directory "%s" does not exist. Please create it first.</error>', $directory)
            );
            return 1;
        }

        $current_version = $this->getApplication()->getVersion();

        $latest_version = $this->getLatestVersion();

        if (!$latest_version) {
            $output->writeln(
                '<error>Cannot get latest version from github.</error>'
            );
            return 1;
        }

        $output->writeln(
            sprintf('<info>Current version: %s</info>', $current_version)
        );
        $output->writeln(
            sprintf('<info>Latest version:  %s</info>', $latest_version['version'])
        );
        if ($current_version == $latest_version['version']) {
            $output->writeln(
                '<info>You are running the latest version.</info>'
            );
            return 0;
        }

        $output->writeln(
            sprintf('<info>Downloading %s ...</info>', $latest_version['url'])
        );

        $phar = file_get_contents($latest_version['url']);

        // Write to /tmp
        $fileSystem->dumpFile('/tmp/geoiplookup', $phar);

        chmod('/tmp/geoiplookup', 0755);

        $output->writeln(
            sprintf('<info>Copying to %s</info>', realpath($directory))
        );

        try {
            $fileSystem->copy('/tmp/geoiplookup', "$directory/geoiplookup", true);
        } catch (IOExceptionInterface $exception) {
            if (trim(`id -u`) !== 0) {
                try {
                    passthru("sudo cp /tmp/geoiplookup $directory/geoiplookup");
                } catch (IOExceptionInterface $exception) {
                    $output->writeln(
                        sprintf('<error>Cannot copy /tmp/geoiplookup to %s</error>', $directory)
                    );
                    return 1;
                }
            } else {
                $output->writeln(
                    sprintf('<error>Cannot copy /tmp/geoiplookup to %s</error>', $directory)
                );
                return 1;
            }
        }

        $output->writeln('Done.');
        return 0;
    }

    /**
     * Return latest release from Github
     *
     * @return Array
     */
    private function getLatestVersion()
    {
        // Github doesn't allow API requests without User-Agent
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP',
                ],
            ],
        ];
        $options = stream_context_create($opts);
        $latest = json_decode(
            file_get_contents(
                'https://api.github.com/repos/axllent/geoiplookup2/releases/latest',
                false,
                $options
            ),
            true
        );

        if (!is_array($latest) || empty($latest['tag_name']) || empty($latest['assets'])) {
            return false;
        }

        $output = [];

        $output['version'] = $latest['tag_name'];

        foreach ($latest['assets'] as $file) {
            if ($file['name'] == 'geoiplookup.phar') {
                $output['url'] = $file['browser_download_url'];
                continue;
            }
        }

        return (!empty($output['version']) && !empty($output['url'])) ? $output : false;
    }
}
