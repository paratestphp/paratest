guard 'phpunit', :all_on_start => false, :tests_path => 'test', :cli => '--colors --bootstrap test/bootstrap.php --exclude-group fixtures' do
  # watch test files
  watch(%r{^.+Test\.php$})

  #watch src
  watch(%r{^src/(.+)\.php}) { |m| "test/#{m[1]}Test\.php" }

end

