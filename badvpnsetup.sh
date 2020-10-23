#!/bin/bash
sleep 2 ; tar -xf badvpn-1.999.128.tar.bz2 && mkdir /etc/badvpn-install && cd /etc/badvpn-install && cmake ~/badvpn-1.999.128 -DBUILD_NOTHING_BY_DEFAULT=1 -DBUILD_UDPGW=1 && make install ;  wget https://raw.githubusercontent.com/powermx/badvpn/master/easyinstall && bash easyinstall
sleep 3 ;echo -e "\e[32mCORRECTO!\e[0m"
