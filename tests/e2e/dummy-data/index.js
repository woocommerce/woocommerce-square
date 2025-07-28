export default {
	creditCard: {
		valid: '4111 1111 1111 1111',
		cvv: '111',
		postalCode: '90001',
		sca: {
			valid: '4310 0000 0020 1019',
			cvv: '111',
			postalCode: '90001',
			verificationCode: '123456',
		},
	},
	giftCard: {
		valid: '7783 3200 0000 0000',
		invalid: '7783320000000001',
		random: 'abcde1234',
	},
	customer: {
		firstname: 'John',
		lastname: 'Doe',
		country: 'US',
		countryBlock: 'United States (US)',
		addr1: '21st Street',
		city: 'LA',
		state: 'CA',
		stateBlock: 'California',
		postcode: '90001',
		phone: '8888888888',
		email: 'john.doe@example.com',
	},
	giftCardSender: {
		senderName: 'John Doe',
		recipientEmail: 'emily@example.com',
		recipientName: 'Emily Doe',
		message: 'Happy Birthday!',
	}
};
