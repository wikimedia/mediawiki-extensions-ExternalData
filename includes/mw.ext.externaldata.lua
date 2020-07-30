--[[
	Registers methods that can be accessed through the Scribunto extension

	@since 0.1

	@licence GNU GPL v2+
	@author Alexander Mashin
]]

-- Variable instantiation
local external = {}

function external.setupInterface()

	-- Variable instantiation
	local php

	-- Interface setup
	external.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register library within the "mw.ext.externaldata" namespace
	mw = mw or {}
	mw.ext = mw.ext or {}

	-- Export functions from php (EDScribunto).
	for name, func in pairs( php ) do
		external[name] = function( parameters )
			local args = {}
			-- Process parameters.
			for key, value in pairs( parameters or {} ) do
				if tonumber( key ) then
					-- parameters without '=', like 'use xpath'.
					args[value] = true
				else
					args[key] = value
				end
			end
			-- PHP exports an associative array with two elements.
			local pair = php[name]( args )
			return pair.values, pair.errors
		end
	end

	mw.ext.externaldata = external
	package.loaded['mw.ext.externaldata'] = external
end

return external
