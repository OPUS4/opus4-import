# -*- mode: ruby -*-
# vi: set ft=ruby :

$software = <<SCRIPT
# Downgrade to PHP 7.1
apt-add-repository -y ppa:ondrej/php
apt-get -yq update
apt-get -yq install php7.1

# Install MYSQL
debconf-set-selections <<< "mysql-server mysql-server/root_password password root"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password root"
apt-get -yq install mysql-server

# Install required PHP packages
apt-get -yq install php7.1-curl
apt-get -yq install php7.1-zip
apt-get -yq install php7.1-dom
apt-get -yq install php7.1-mysql

# Install required tools
apt-get -yq install ant
apt-get -yq install unzip
SCRIPT

$composer = <<SCRIPT
cd /vagrant
bin/install-composer.sh
bin/composer update
SCRIPT

$database = <<SCRIPT
export MYSQL_PWD=root && mysql --default-character-set=utf8 -h 'localhost' -P '3306' -u 'root' -v -e "CREATE DATABASE IF NOT EXISTS opusdb DEFAULT CHARACTER SET = UTF8 DEFAULT COLLATE = UTF8_GENERAL_CI; CREATE USER IF NOT EXISTS 'opus4admin'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root'; GRANT ALL PRIVILEGES ON opusdb.* TO 'opus4admin'@'localhost'; CREATE USER IF NOT EXISTS 'opus4'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root'; GRANT SELECT,INSERT,UPDATE,DELETE ON opusdb.* TO 'opus4'@'localhost'; FLUSH PRIVILEGES;"
SCRIPT

$opus = <<SCRIPT
cd /vagrant
ant prepare-workspace prepare-config -DdbUserPassword=root -DdbAdminPassword=root
php test/TestAsset/createdb.php
SCRIPT

$environment = <<SCRIPT
if ! grep "cd /vagrant" /home/vagrant/.profile > /dev/null; then
  echo "cd /vagrant" >> /home/vagrant/.profile
fi
if ! grep "PATH=/vagrant/bin" /home/vagrant/.bashrc > /dev/null; then
  echo "export PATH=/vagrant/bin:$PATH" >> /home/vagrant/.bashrc
fi
SCRIPT

$help = <<SCRIPT
echo "Use 'vagrant ssh' to log into VM and 'logout' to leave it."
echo "In VM use:"
echo "'composer test' for running tests"
echo "'composer update' to update dependencies"
echo "'composer cs-check' to check coding style"
echo "'composer cs-fix' to automatically fix basic style problems"
SCRIPT

Vagrant.configure("2") do |config|
  config.vm.box = "bento/ubuntu-20.04"

  config.vm.provision "Install required software...", type: "shell", inline: $software
  config.vm.provision "Install Composer dependencies...", type: "shell", privileged: false, inline: $composer
  config.vm.provision "Create database...", type: "shell", inline: $database
  config.vm.provision "Configure OPUS 4...", type: "shell", privileged: false, inline: $opus
  config.vm.provision "Setup environment...", type: "shell", inline: $environment
  config.vm.provision "Information", type: "shell", privileged: false, run: "always", inline: $help
end
