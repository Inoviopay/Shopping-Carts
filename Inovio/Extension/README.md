Inovio Magento Module Installation Documentation 

1.	Unzip Inovio Module and put it into app/code folder.
2.	Enable Invio Payment module using below steps:
	
	Magento admin:

	a- Login into Magento admin panel using your magento credential.
	b- Navigate Stores => Configuration => Advanced => Advanced
	c- Enable Inovio Payment Gateway
	d- After enable remove Cache

3.  Inovio Merchant Configuration:-
	
	a- Navigate Stores => Configuration => Sales=>Payment Methods=> Inovio Payment Gateway
	b- Here you can setup merchant information with advance parameters.
	c- After setup merhcant information remove cache

4- Now you can see Inovio Payment method on checkout page to purchase product.
5- You can check log functionality in magento/var/log/system.log

