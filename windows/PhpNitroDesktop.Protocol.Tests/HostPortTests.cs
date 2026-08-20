using PhpNitroDesktop.Protocol;
using Xunit;

namespace PhpNitroDesktop.Protocol.Tests;

// The Windows counterpart of ios/Tests/PhpNitroGoTests/HostPortTests.swift
// — same cases, ported case for case against this project's own local
// HostPort copy (see HostPort.cs's own comment for why it's a copy, not
// a shared dependency).
public class HostPortTests
{
    [Fact]
    public void ParsesAPlainHostPort()
    {
        var parsed = HostPort.Parse("192.168.1.23:8090");

        Assert.Equal("192.168.1.23", parsed?.Host);
        Assert.Equal(8090, parsed?.Port);
    }

    [Fact]
    public void StripsAnHttpScheme()
    {
        var parsed = HostPort.Parse("http://192.168.1.23:8090");

        Assert.Equal("192.168.1.23", parsed?.Host);
        Assert.Equal(8090, parsed?.Port);
    }

    [Fact]
    public void StripsAnHttpsSchemeAndTrailingSlash()
    {
        var parsed = HostPort.Parse("https://192.168.1.23:8090/");

        Assert.Equal("192.168.1.23", parsed?.Host);
        Assert.Equal(8090, parsed?.Port);
    }

    [Fact]
    public void TrimsWhitespace()
    {
        var parsed = HostPort.Parse("  192.168.1.23:8090  \n");

        Assert.Equal("192.168.1.23", parsed?.Host);
        Assert.Equal(8090, parsed?.Port);
    }

    [Fact]
    public void RejectsMissingColon()
    {
        Assert.Null(HostPort.Parse("192.168.1.23"));
    }

    [Fact]
    public void RejectsMissingHost()
    {
        Assert.Null(HostPort.Parse(":8090"));
    }

    [Fact]
    public void RejectsMissingPort()
    {
        Assert.Null(HostPort.Parse("192.168.1.23:"));
    }

    [Fact]
    public void RejectsANonNumericPort()
    {
        Assert.Null(HostPort.Parse("192.168.1.23:abc"));
    }

    [Fact]
    public void RejectsAPortOutOfRange()
    {
        Assert.Null(HostPort.Parse("192.168.1.23:70000"));
        Assert.Null(HostPort.Parse("192.168.1.23:0"));
    }

    [Fact]
    public void RejectsAnEmptyString()
    {
        Assert.Null(HostPort.Parse(""));
    }
}
