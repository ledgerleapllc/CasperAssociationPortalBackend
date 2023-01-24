#!/usr/bin/env python3
import os, sys, subprocess, argparse

# os.system('clear')
print("")

class Wizard:
	args = None
	action = None
	endpoint_name = None
	authorization_header = ''
	base_dir = None
	http_method = 'GET'
	description = 'My new endpoint description'

	def __init__(self):
		self.base_dir = "%s%s" % (os.path.dirname(os.path.realpath(__file__)), '/')

		parser = argparse.ArgumentParser()
		parser.add_argument("args", nargs='*')
		args = parser.parse_args()
		self.args = args.args

		if len(self.args) > 0:
			self.action = self.args[0]

		if (not self.action or self.action == ''):
			print('Usage:')
			print(' ./wizard <endpoint-type> <endpoint-name> <method>')
			print()
			print('Types:   user, admin, public, cron')
			print('Methods: GET, POST, PUT')
			print()
			print('Example:')
			print(' ./wizard.py user register-individual post')
			print()
			exit(0)

		if (
			self.action != 'user' and
			self.action != 'admin' and
			self.action != 'public' and
			self.action != 'cron'
		):
			print('Invalid action specified')
			print(' 1. user')
			print(' 2. admin')
			print(' 3. public')
			print(' 4. cron')
			exit(0)

		if (
			self.action == 'user' or
			self.action == 'admin'
		):
			self.authorization_header = 'HEADER Authorization: Bearer'

		if (self.action == 'cron'):
			self.authorization_header = 'HEADER Authorization: Token'

		if (
			self.action == 'user' or 
			self.action == 'admin' or 
			self.action == 'public' or 
			self.action == 'cron'
		):
			if len(self.args) > 1:
				self.endpoint_name = self.args[1]

		if not self.endpoint_name or self.endpoint_name == '':
			print('Endpoint name required')
			exit(0)

		if (
			len(self.args) > 2 and (
				self.args[2] == 'GET' or
				self.args[2] == 'POST' or
				self.args[2] == 'PUT' or
				self.args[2] == 'get' or
				self.args[2] == 'post' or
				self.args[2] == 'put'
			)
		):
			self.http_method = self.args[2].upper()
		else:
			print('Using default method GET')

	def camel_case(self, text, capital = False):
		s = text.replace("-", " ").replace("_", " ")
		s = s.split()

		if len(text) == 0:
			return text

		first_letter = s[0].capitalize() if capital else s[0]
		return first_letter + ''.join(i.capitalize() for i in s[1:])

	def endpoint_already_exists(self):
		try:
			f = open(
				"%spublic/%s_api/%s.php" % (
					self.base_dir,
					self.action,
					self.endpoint_name
				), 'r'
			)
			f.close()
			return True
		except Exception as e:
			htaccess_file = open("%spublic/.htaccess" % self.base_dir, 'r')
			htaccess = htaccess_file.readlines()
			htaccess_file.close()

			for line in htaccess:
				if ("^%s/%s/?$" % (self.action, self.endpoint_name)) in line:
					return True

			return False

	def create_endpoint(self):
		if self.endpoint_already_exists():
			print('Endpoint already exists')
			exit(1)


		# create endpoint route
		print(
			"[+] Creating endpoint route: %s\n" % (
				self.endpoint_name
			)
		)

		htaccess_file = open("%spublic/.htaccess" % self.base_dir, 'r')
		htaccess = htaccess_file.read()
		htaccess_file.close()
		htaccess_split = htaccess.split('#')
		htaccess_chunk = None

		for i, chunk in enumerate(htaccess_split):
			if ('%s API' % self.action.upper()) in chunk:
				htaccess_chunk = chunk.rstrip()
				htaccess_chunk = htaccess_chunk + (
					"\n" +
					"RewriteRule ^" +
					self.action +
					"/" +
					self.endpoint_name +
					"/?$ /" +
					self.action +
					"_api/" +
					self.endpoint_name +
					".php [NC]\n\n"
				)
				htaccess_split[i] = htaccess_chunk

		htaccess_joined = "#".join(htaccess_split)
		htaccess_chunk_lines = htaccess_chunk.split("\n")

		for line in htaccess_chunk_lines:
			print("\033[1;32;48m" + line + "\033[00m" if self.endpoint_name in line else line)


		# save endpoint rule to .htaccess
		htaccess_file = open("%spublic/.htaccess" % self.base_dir, 'w')
		htaccess_file.write(htaccess_joined)
		htaccess_file.close()


		# create endpoint file
		print(
			"\n[+] Creating endpoint file: %s\n" % (
				self.endpoint_name
			)
		)

		f = open(
			"%spublic/%s_api/%s.php" % (
				self.base_dir,
				self.action,
				self.endpoint_name
			), 'w'
		)
		f.write('''<?php
include_once('../../core.php');
/**
 *
 * %s /%s/%s
 *
 * %s
 *
 * %s
 *
 * @api
 * @param    string  $param1
 * @param    int     $param2
 *
 */
class %s%s extends Endpoints {
	/* 
	GET/POST/PUT Parameters must be placed in constructor so PHPDocumentor picks them up.
	__construct($param1 = '', $param2 = 0)
	*/
	function __construct() {
		global $db, $helper;

		/* Require this method. Can pass array */
		require_method('%s');

		/* 
		Fetches user data using the bearer token. Can pass required clearance level as argument.
		authenticate_session(0) = Clearance level 0 - test-user
		authenticate_session(1) = Clearance level 1 - user (default)
		authenticate_session(2) = Clearance level 2 - sub-admin
		authenticate_session(3) = Clearance level 3 - admin
		authenticate_session(4) = Clearance level 4 - super-admin
		*/
		$auth = authenticate_session();

		/* Extract user data from auth'd bearer token. */
		$%s_guid = $auth['guid'] ?? '';

		/* Extract real parameters */
		// $param1 = parent::$params['param1'] ?? '';
		// $param2 = parent::$params['param2'] ?? 0;

		// Code starts here
	}
}
new %s%s();
''' % (
	self.http_method,
	self.action,
	self.endpoint_name,
	self.authorization_header,
	self.description,
	self.action.capitalize(),
	self.camel_case(self.endpoint_name, True),
	self.http_method,
	self.action,
	self.action.capitalize(),
	self.camel_case(self.endpoint_name, True)
));
		f.close()
		ls = os.listdir("%spublic/%s_api/" % (self.base_dir, self.action))
		for item in ls:
			line = ("public/%s_api/" % self.action) + item
			print("\033[1;32;48m" + line + "\033[00m" if self.endpoint_name in item else line)


wizard = Wizard()
wizard.create_endpoint()