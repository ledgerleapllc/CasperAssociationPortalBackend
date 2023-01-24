#!/usr/bin/env python3
import rstr, argparse

parser  = argparse.ArgumentParser()
parser.add_argument('args', nargs="*")
args    = parser.parse_args()
args    = args.args
pattern = ''

if len(args) > 0:
	pattern = args[0]
else:
	print('')

# /(01|02)([a-fA-F0-9]){64,66}$/
# 2f2830317c303229285b612d66412d46302d395d297b36342c36367d242f

pattern_bytes  = bytes.fromhex(pattern)
pattern_string = pattern_bytes.decode('utf-8')
pattern_string = pattern_string.replace('/', '')

# print(pattern_string)

s = rstr.xeger(pattern_string)

print(s)