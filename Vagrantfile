# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|

  config.vm.box = "rasmus/php7dev"
  config.vm.box_check_update = false

  #config.vm.network "forwarded_port", guest: 80, host: 8080
  #config.vm.synced_folder "../data", "/vagrant_data"

  config.vm.provider "virtualbox" do |vb|
		#vb.gui = true
		vb.memory = "1024"
  end

  config.vm.provision "shell", inline: <<-SHELL
		sudo apt-get update
		sudo apt-get install -y libsodium-dev
  SHELL
  
end
